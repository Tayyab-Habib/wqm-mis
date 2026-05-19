<?php

namespace App\Http\Controllers\Xen;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WaterSamples\WaterSample;
use App\Models\WaterSamples\WaterSampleTest;
use App\Models\WaterSamples\WaterSampleAction;
use App\Models\User;
use App\Enums\UserRoleEnum;
use App\Enums\WaterSampleTestResultEnum;
use App\Enums\WaterSampleTestStatusEnum;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as Http;

class XenDashboardController extends Controller
{
    /**
     * 403 helper — unscoped admins bypass; everyone else must hold $perm.
     * Mirrors the gating pattern in Secretary/CE portal controllers.
     */
    private function gate(string $perm): ?JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], Http::HTTP_UNAUTHORIZED);
        }
        if ($user->isUnscoped() || $user->can($perm)) {
            return null;
        }
        return response()->json([
            'message' => 'Not authorized to access this XEN portal screen',
        ], Http::HTTP_FORBIDDEN);
    }


    /**
     * Get the unfit sample IDs for a given division by looking at water_sample_tests.
     * A sample is considered "unfit" when its latest completed test round has result = UNFIT.
     */
    private function getUnfitSampleIds(?int $phedDivisionId): \Illuminate\Support\Collection
    {
        // A sample is "unfit" when any of its test rounds has result = UNFIT
        // We don't restrict to is_final because many older records are not marked final
        $query = DB::table('water_sample_tests as wst')
            ->join('water_samples as ws', 'wst.water_sample_id', '=', 'ws.id')
            ->where('wst.result', WaterSampleTestResultEnum::UNFIT->value)
            ->whereNull('ws.deleted_at');

        if ($phedDivisionId) {
            $query->where('ws.phed_division_id', $phedDivisionId);
        }

        return $query->distinct()->pluck('wst.water_sample_id');
    }

    public function index(Request $request)
    {
        if ($r = $this->gate('view_xen_dashboard')) return $r;
        $user = auth()->user();
        $user->load(['phedDivision', 'district', 'circle', 'region']);
        $phedDivisionId = $user->phed_division_id;

        // ── Unfit Samples ───────────────────────────────────────────────────
        // Samples whose latest final test is UNFIT (based on water_sample_tests)
        $unfitSampleIds = $this->getUnfitSampleIds($phedDivisionId);

        $unfitSamples = WaterSample::whereIn('id', $unfitSampleIds)
            ->with([
                'waterScheme:id,name',
                'phedDivision:id,name',
                'district:id,name',
                'tests' => function ($q) {
                    $q->orderByDesc('round');
                },
            ])
            ->withCount([
                'tests',
                'tests as unfit_tests_count' => function ($q) {
                    $q->where('result', WaterSampleTestResultEnum::UNFIT->value);
                },
            ])
            ->get()
            ->map(function ($sample) {
                return $this->formatUnfitSampleWithTimeline($sample);
            });

        // ── Retest Samples ───────────────────────────────────────────────────
        // Tests that are round > 0 for unfit sample IDs
        $retestSamples = WaterSampleTest::whereIn('water_sample_id', $unfitSampleIds)
            ->where('round', '>', 0)
            ->with([
                'waterSample.waterScheme:id,name',
                'waterSample.tests' => function ($q) {
                    $q->orderByDesc('round');
                }
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($test) {
                $statusVal = $test->status instanceof \BackedEnum ? $test->status->value : $test->status;
                $statusEnum = $test->status instanceof \BackedEnum ? $test->status : \App\Enums\WaterSampleTestStatusEnum::tryFrom($statusVal);

                $resultVal = $test->result instanceof \BackedEnum ? $test->result->value : $test->result;
                $resultEnum = $test->result instanceof \BackedEnum ? $test->result : \App\Enums\WaterSampleTestResultEnum::tryFrom($resultVal);

                $waterSample = $test->waterSample;
                $timeline = [];
                if ($waterSample) {
                    $formatted = $this->formatUnfitSampleWithTimeline($waterSample);
                    $timeline = $formatted['timeline'] ?? [];
                }

                return [
                    'id' => $test->id,
                    'water_sample_id' => $test->water_sample_id,
                    'slug' => $test->waterSample?->slug ?? $test->water_sample_id,
                    'sample_name' => $test->waterSample?->wss_name ?? $test->waterSample?->slug,
                    'water_scheme_name' => $test->waterSample?->waterScheme?->name ?? 'N/A',
                    'analyzed_at' => $test->analyzed_at ?? $test->created_at,
                    'current_round' => $test->round,
                    'status' => $statusVal,
                    'status_label' => $statusEnum?->label() ?? 'Pending',
                    'status_badge' => $statusEnum?->badgeClass() ?? 'bg-secondary',
                    'result' => $resultVal,
                    'result_label' => $resultEnum?->label() ?? '—',
                    'timeline' => $timeline,
                ];
            });

        // ── SLA Breached ────────────────────────────────────────────────────
        $slaBreachedQuery = DB::table('notifications')
            ->where('type_key', 'SAMPLE_UNFIT')
            ->whereNotNull('due_at')
            ->whereNull('action_taken_at')
            ->where('due_at', '<', now());

        if ($phedDivisionId) {
            $slaBreachedQuery->whereIn('water_sample_id', function ($query) use ($phedDivisionId) {
                $query->select('id')->from('water_samples')->where('phed_division_id', $phedDivisionId);
            });
        }

        $slaBreachedIds = $slaBreachedQuery->pluck('water_sample_id')->unique();
        $slaBreached = WaterSample::whereIn('id', $slaBreachedIds)
            ->with('waterScheme:id,name')
            ->get()
            ->map(function ($s) use ($slaBreachedQuery) {
                $notif = DB::table('notifications')
                    ->where('water_sample_id', $s->id)
                    ->where('type_key', 'SAMPLE_UNFIT')
                    ->orderByDesc('created_at')
                    ->first();
                $s->due_at = $notif->due_at ?? null;
                return $s;
            });

        // ── Notifications ───────────────────────────────────────────────────
        $notifications = DB::table('notifications')
            ->where('notifiable_id', $user->id)
            ->where('notifiable_type', User::class)
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get()
            ->map(function ($notif) {
                $data = is_string($notif->data ?? '') ? json_decode($notif->data, true) : ($notif->data ?? []);
                return [
                    'id' => $notif->id,
                    'created_at' => $notif->created_at,
                    'data' => array_merge($data ?? [], [
                        'type' => $notif->type_key ?? null,
                        'message' => $data['message'] ?? ('Update regarding sample #' . $notif->water_sample_id),
                        'sample_id' => $notif->water_sample_id ?? null,
                        'due_at' => $notif->due_at ?? null,
                        'severity' => (isset($notif->due_at) && Carbon::parse($notif->due_at)->isPast() && !$notif->action_taken_at) ? 'high' : 'medium',
                    ]),
                ];
            });

        // ── Stats ───────────────────────────────────────────────────────────
        $stats = [
            'unfit_no_action' => $unfitSamples->filter(function ($s) {
                return !DB::table('water_sample_actions')->where('water_sample_id', $s['id'])->exists();
            })->count(),
            'retests_pending' => $retestSamples->whereIn('status', [WaterSampleTestStatusEnum::PENDING->value, WaterSampleTestStatusEnum::IN_PROGRESS->value])->count(),
            'sla_breached' => $slaBreached->count(),
            'resolved' => WaterSample::whereIn('id', function ($q) use ($phedDivisionId) {
                // Resolved = sample had UNFIT then a later test is FIT (final)
                $q->select('water_sample_id')
                    ->from('water_sample_tests')
                    ->where('result', WaterSampleTestResultEnum::FIT->value)
                    ->where('is_final', 1)
                    ->when($phedDivisionId, function ($q2) use ($phedDivisionId) {
                    $q2->whereIn('water_sample_id', function ($q3) use ($phedDivisionId) {
                        $q3->select('id')->from('water_samples')->where('phed_division_id', $phedDivisionId);
                    });
                })
                    ->where('updated_at', '>=', now()->startOfMonth());
            })->count(),
        ];

        return response()->json([
            'stats' => $stats,
            'retest_samples' => $retestSamples,
            'unfit_samples' => $unfitSamples,
            'sla_breached' => $slaBreached,
            'notifications' => $notifications,
            'user_info' => [
                'name' => $user->name,
                'division' => $user->phedDivision?->name ?? 'N/A',
                'district' => $user->district?->name ?? 'N/A',
                'circle' => $user->circle?->name ?? 'N/A',
                'region' => $user->region?->name ?? 'N/A',
                'phone' => $user->phone ?? 'N/A',
            ],
        ]);
    }

    public function trail(Request $request)
    {
        if ($r = $this->gate('view_xen_unfit_trail')) return $r;
        $user = auth()->user();
        $phedDivisionId = $user->phed_division_id;
        $type = $request->query('type', 'unfit');

        $unfitSampleIds = $this->getUnfitSampleIds($phedDivisionId);

        if ($type === 'retest') {
            // For retests, we find tests that are round > 0 for the unfit samples
            // Or tests that are pending for these samples. Let's just fetch tests that are retests
            $samples = WaterSampleTest::whereIn('water_sample_id', $unfitSampleIds)
                ->where('round', '>', 0)
                ->with([
                    'waterSample.waterScheme:id,name',
                    'waterSample.tests' => function ($q) {
                        $q->orderByDesc('round');
                    }
                ])
                ->orderByDesc('created_at')
                ->get()
                ->map(function ($test) {
                    $statusVal = $test->status instanceof \BackedEnum ? $test->status->value : $test->status;
                    $statusEnum = $test->status instanceof \BackedEnum ? $test->status : \App\Enums\WaterSampleTestStatusEnum::tryFrom($statusVal);

                    $resultVal = $test->result instanceof \BackedEnum ? $test->result->value : $test->result;
                    $resultEnum = $test->result instanceof \BackedEnum ? $test->result : \App\Enums\WaterSampleTestResultEnum::tryFrom($resultVal);

                    $waterSample = $test->waterSample;
                    $timeline = [];
                    if ($waterSample) {
                        $formatted = $this->formatUnfitSampleWithTimeline($waterSample);
                        $timeline = $formatted['timeline'] ?? [];
                    }

                    return [
                        'id' => $test->id,
                        'water_sample_id' => $test->water_sample_id,
                        'slug' => $test->waterSample?->slug ?? $test->water_sample_id,
                        'sample_name' => $test->waterSample?->wss_name ?? $test->waterSample?->slug,
                        'water_scheme_name' => $test->waterSample?->waterScheme?->name ?? 'N/A',
                        'analyzed_at' => $test->analyzed_at ?? $test->created_at,
                        'current_round' => $test->round,
                        'status' => $statusVal,
                        'status_label' => $statusEnum?->label() ?? 'Pending',
                        'status_badge' => $statusEnum?->badgeClass() ?? 'bg-secondary',
                        'result' => $resultVal,
                        'result_label' => $resultEnum?->label() ?? '—',
                        'cause' => 'Lab Test',
                        'unfit_parameters' => 'See Details',
                        'timeline' => $timeline,
                    ];
                });

            $stats = [
                'total' => $samples->count(),
                'awaiting_analysis' => $samples->where('status', WaterSampleTestStatusEnum::PENDING->value)->count(),
                'fit_resolved' => $samples->where('result', WaterSampleTestResultEnum::FIT->value)->count(),
                'still_unfit' => $samples->where('result', WaterSampleTestResultEnum::UNFIT->value)->count(),
            ];

            return response()->json([
                'samples' => $samples,
                'stats' => $stats,
            ]);
        }

        // Only show samples whose CURRENT status is UNFIT — once a retest is registered
        // the sample's current_status drops to PENDING/IN_PROGRESS and it disappears from
        // this page; if the retest comes back UNFIT it lands back here.
        $samples = WaterSample::whereIn('id', $unfitSampleIds)
            ->where('current_status', \App\Enums\WaterSampleCurrentStatusEnum::UNFIT->value)
            ->with([
                'waterScheme:id,name',
                'phedDivision:id,name',
                'district:id,name',
                'tests' => function ($q) {
                    $q->orderByDesc('round');
                },
            ])
            ->get()
            ->map(function ($sample) {
                $base = $this->formatUnfitSampleWithTimeline($sample);

                // Enrich for parity with the admin Unfit Sample Trail page
                // (display order = ascending by round, regardless of load order)
                $tests = $sample->tests->sortBy('round')->values()->map(function ($t) {
                    $statusVal = $t->status instanceof \BackedEnum ? $t->status->value : $t->status;
                    $resultVal = $t->result instanceof \BackedEnum ? $t->result->value : $t->result;
                    $statusEnum = \App\Enums\WaterSampleTestStatusEnum::tryFrom((int) $statusVal);
                    $resultEnum = $resultVal !== null ? \App\Enums\WaterSampleTestResultEnum::tryFrom((int) $resultVal) : null;
                    return [
                        'id'          => $t->id,
                        'round'       => $t->round,
                        'status'      => $statusEnum?->label() ?? 'Pending',
                        'result'      => $resultEnum?->label() ?? null,
                        'sampled_at'  => $t->getRawOriginal('sampled_at'),
                        'analyzed_at' => $t->getRawOriginal('analyzed_at'),
                    ];
                })->values();

                $base['phed_division']   = $sample->phedDivision ? ['id' => $sample->phedDivision->id, 'name' => $sample->phedDivision->name] : null;
                $base['district']        = $sample->district ? ['id' => $sample->district->id, 'name' => $sample->district->name] : null;
                $base['water_scheme']    = $sample->waterScheme ? ['id' => $sample->waterScheme->id, 'name' => $sample->waterScheme->name] : null;
                $base['sampled_at']      = $sample->getRawOriginal('sampled_at');
                $base['current_status']  = (int) ($sample->current_status instanceof \BackedEnum ? $sample->current_status->value : $sample->current_status);
                $base['is_closed']       = (bool) $sample->is_closed;
                $base['transferred_to_secretary_at'] = $sample->transferred_to_secretary_at;
                $base['tests']           = $tests;

                return $base;
            });

        $stats = [
            'total' => $samples->count(),
            'no_action' => $samples->where('status', 'no_action')->count(),
            'action_taken' => $samples->where('status', 'action_taken')->count(),
            'resolved' => $samples->where('status', 'resolved')->count(),
        ];

        return response()->json([
            'samples' => $samples,
            'stats' => $stats,
        ]);
    }

    public function requestRetest(Request $request)
    {
        // WRITE — gated on submit_xen_retest (separate perm from view ones
        // so admins can grant read-only access to XEN screens without
        // letting the user actually trigger a retest).
        if ($r = $this->gate('submit_xen_retest')) return $r;

        $request->validate([
            'water_sample_id' => 'required|exists:water_samples,id',
            'action_type' => 'required|string',
            'details' => 'nullable|string',
        ]);

        $sample = WaterSample::findOrFail($request->water_sample_id);

        DB::beginTransaction();
        try {
            $latestTest = WaterSampleTest::where('water_sample_id', $sample->id)
                ->orderByDesc('round')
                ->first();

            $action = WaterSampleAction::create([
                'water_sample_id' => $sample->id,
                'user_id' => auth()->id(),
                'round' => $latestTest?->round ?? 0,
                'action_type' => $request->action_type,
                'details' => $request->details,
                'action_date' => $request->action_date ?? now()->toDateString(),
            ]);

            // Mark existing UNFIT notifications as action taken
            DB::table('notifications')
                ->where('water_sample_id', $sample->id)
                ->where('type_key', 'SAMPLE_UNFIT')
                ->whereNull('action_taken_at')
                ->update(['action_taken_at' => now(), 'status' => 2]);

            // Notify creator/lab that retest is requested
            DB::table('notifications')->insert([
                'id' => \Illuminate\Support\Str::uuid(),
                'type' => 'App\Notifications\RetestRequested',
                'notifiable_type' => User::class,
                'notifiable_id' => $sample->created_by,
                'data' => json_encode([
                    'message' => 'XEN has requested a retest for sample #' . $sample->id,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
                'water_sample_id' => $sample->id,
                'round' => ($latestTest?->round ?? 0) + 1,
                'role' => 'STAFF',
                'status' => 1,
                'type_key' => 'RETEST_REQUESTED',
                'notified_at' => now(),
            ]);

            DB::commit();
            return response()->json([
                'message' => 'Action logged and retest requested successfully',
                'action' => $action,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Hand off a Persistent Unfit sample to the Secretary for a Fate Decision.
     *
     * Only allowed for samples that have already exhausted at least two retest
     * rounds with UNFIT results — at that point the XEN can't resolve it via
     * more retests, so authority passes upward. We stamp the audit columns on
     * water_samples and drop a notification row for every Secretary user so
     * the Secretary's queue surfaces it immediately.
     */
    public function transferToSecretary(Request $request, int $id)
    {
        if ($r = $this->gate('submit_xen_retest')) return $r;

        $request->validate([
            'remarks' => 'nullable|string|max:2000',
        ]);

        $sample = WaterSample::with('tests')->findOrFail($id);

        // Guard: must be Persistent Unfit (R2+ UNFIT) before XEN can escalate.
        $isUnfit = (int) ($sample->current_status instanceof \BackedEnum
            ? $sample->current_status->value
            : $sample->current_status) === \App\Enums\WaterSampleCurrentStatusEnum::UNFIT->value;
        if (!$isUnfit || (int) $sample->current_round < 2) {
            return response()->json([
                'message' => 'Only samples that are still UNFIT after two retests can be transferred.',
            ], 422);
        }

        if (!is_null($sample->transferred_to_secretary_at)) {
            return response()->json([
                'message' => 'This sample has already been transferred to the Secretary.',
                'transferred_at' => $sample->transferred_to_secretary_at,
            ], 200);
        }

        DB::beginTransaction();
        try {
            $sample->forceFill([
                'transferred_to_secretary_at'      => now(),
                'transferred_to_secretary_by'      => auth()->id(),
                'transferred_to_secretary_remarks' => $request->input('remarks'),
            ])->save();

            // Drop a notification for every Secretary user so the queue + bell
            // updates instantly without polling. Multiple secretaries are
            // possible; we notify each so any of them can act.
            $secretaryIds = User::role('secretary')->pluck('id');
            foreach ($secretaryIds as $secId) {
                DB::table('notifications')->insert([
                    'id' => \Illuminate\Support\Str::uuid(),
                    'type' => 'App\Notifications\FateDecisionRequested',
                    'notifiable_type' => User::class,
                    'notifiable_id' => $secId,
                    'data' => json_encode([
                        'message' => 'XEN has transferred sample ' . $sample->slug . ' for Fate Decision.',
                        'sample_slug' => $sample->slug,
                        'remarks' => $request->input('remarks'),
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                    'water_sample_id' => $sample->id,
                    'round' => $sample->current_round,
                    'role' => 'SECRETARY',
                    'status' => 1,
                    'type_key' => 'FATE_DECISION_REQUESTED',
                    'notified_at' => now(),
                ]);
            }

            DB::commit();
            return response()->json([
                'message' => 'Transferred to Secretary for Fate Decision.',
                'transferred_at' => $sample->transferred_to_secretary_at,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Full trail data for one sample — used by the XEN Trail modal.
     * Returns: timeline (test/action/notification events), sidebar sample info,
     * notifications panel, and the list of action types the log form offers.
     */
    public function trailDetail($id)
    {
        if ($r = $this->gate('view_xen_unfit_trail')) return $r;

        $user = auth()->user()->load(['phedDivision', 'district']);

        $sample = WaterSample::query()
            ->with([
                'waterScheme:id,name',
                'phedDivision:id,name',
                'district:id,name',
                'tests' => fn($q) => $q->orderBy('round'),
            ])
            ->when($user->phed_division_id, fn($q) => $q->where('phed_division_id', $user->phed_division_id))
            ->findOrFail($id);

        $formatted = $this->formatUnfitSampleWithTimeline($sample);

        // Notifications list for the right sidebar
        $notifs = DB::table('notifications as n')
            ->leftJoin('users as u', 'n.notifiable_id', '=', 'u.id')
            ->where('n.water_sample_id', $sample->id)
            ->orderBy('n.created_at')
            ->select('n.id', 'n.created_at', 'n.type_key', 'n.data', 'n.action_taken_at', 'u.name as user_name')
            ->get()
            ->map(function ($n) {
                $data = is_string($n->data ?? '') ? json_decode($n->data, true) : ($n->data ?? []);
                return [
                    'id'          => $n->id,
                    'created_at'  => $n->created_at,
                    'type_key'    => $n->type_key,
                    'message'     => $data['message'] ?? null,
                    'recipient'   => $n->user_name,
                    'status'      => $n->action_taken_at ? 'Acknowledged' : 'Initial',
                ];
            });

        // Cause / parameter — derive from latest unfit test remarks if present
        $unfitTest = $sample->tests->first(function ($t) {
            $r = $t->result instanceof \BackedEnum ? $t->result->value : $t->result;
            return (int) $r === WaterSampleTestResultEnum::UNFIT->value;
        });
        $causeText = $unfitTest?->remarks ?: ($formatted['cause'] ?? 'Lab Test');

        return response()->json(array_merge($formatted, [
            'sample_info' => [
                'sample_id'     => $sample->slug,
                'wss'           => $sample->waterScheme?->name ?? '—',
                'phed_division' => $sample->phedDivision?->name ?? '—',
                'xen_name'      => $user->name ?? '—',
                'cause'         => $causeText,
            ],
            'notifications_panel' => $notifs,
            'action_types' => [
                'Chlorination Done',
                'Source Cleaned',
                'Inspected',
                'Maintenance Done',
                'Operator Trained',
                'Source Replaced',
                'Retest Requested',
                'Other',
            ],
        ]));
    }

    private function formatUnfitSampleWithTimeline($sample)
    {
        $hasAction = DB::table('water_sample_actions')
            ->where('water_sample_id', $sample->id)
            ->exists();

        // Check if there's a later FIT result (resolved)
        $isResolved = WaterSampleTest::where('water_sample_id', $sample->id)
            ->where('result', WaterSampleTestResultEnum::FIT->value)
            ->where('is_final', 1)
            ->exists();

        $status = $isResolved ? 'resolved' : ($hasAction ? 'action_taken' : 'no_action');
        $statusBadge = $isResolved ? 'bg-success' : ($hasAction ? 'bg-warning' : 'bg-danger');

        $latestTest = $sample->tests->first();

        // Build SLA info
        $unfitNotification = DB::table('notifications')
            ->where('water_sample_id', $sample->id)
            ->where('type_key', 'SAMPLE_UNFIT')
            ->orderByDesc('created_at')
            ->first();

        $slaText = 'N/A';
        $slaBadge = 'bg-secondary';

        if ($unfitNotification && $unfitNotification->due_at) {
            $dueAt = \Carbon\Carbon::parse($unfitNotification->due_at);
            $actionTakenAt = $unfitNotification->action_taken_at ? \Carbon\Carbon::parse($unfitNotification->action_taken_at) : null;

            if ($actionTakenAt) {
                if ($actionTakenAt->lessThanOrEqualTo($dueAt)) {
                    $slaText = 'On Time Action';
                    $slaBadge = 'bg-success';
                } else {
                    $slaText = 'Late Action';
                    $slaBadge = 'bg-warning text-dark';
                }
            } else {
                $now = now();
                if ($now->greaterThan($dueAt)) {
                    $slaText = 'Overdue';
                    $slaBadge = 'bg-danger';
                } else {
                    $diff = $now->diff($dueAt);
                    $parts = [];
                    if ($diff->d > 0) $parts[] = $diff->d . 'd';
                    if ($diff->h > 0) $parts[] = $diff->h . 'h';
                    if (empty($parts)) $parts[] = '< 1h';
                    $slaText = implode(' ', $parts) . ' left';
                    $slaBadge = $diff->d < 3 ? 'bg-warning text-dark' : 'bg-info text-dark';
                }
            }
        }

        // Build Timeline
        $actions = DB::table('water_sample_actions')
            ->where('water_sample_id', $sample->id)
            ->leftJoin('users', 'water_sample_actions.user_id', '=', 'users.id')
            ->select('water_sample_actions.*', 'users.name as user_name')
            ->get();

        $notifications = DB::table('notifications')
            ->where('water_sample_id', $sample->id)
            ->get();

        $timeline = [];

        foreach ($sample->tests as $test) {
            $title = $test->round == 0 ? 'Unfit Result Recorded' : 'Retest R' . $test->round . ' Collected';
            if ($test->round > 0 && $test->result) {
                $title = 'Retest R' . $test->round . ' Analyzed';
            }
            $timeline[] = [
                'type' => 'test',
                'title' => $title,
                'details' => $test->round == 0 ? 'Initial sample tested.' : 'Sample registered/analyzed.',
                'date' => $test->analyzed_at ?? $test->created_at,
                'user' => 'Lab',
                'round' => $test->round
            ];
        }

        foreach ($actions as $action) {
            $timeline[] = [
                'type' => 'action',
                'title' => $action->action_type . ' R' . $action->round,
                'details' => $action->details,
                'date' => $action->action_date ?? $action->created_at,
                'user' => $action->user_name ?? 'System',
                'round' => $action->round
            ];
        }

        foreach ($notifications as $notification) {
            $data = json_decode($notification->data, true);
            $timeline[] = [
                'type' => 'notification',
                'title' => 'Notification Sent',
                'details' => $data['message'] ?? $data['content'] ?? 'Notification',
                'date' => $notification->created_at,
                'user' => 'System',
                'round' => $data['round'] ?? null
            ];
        }

        usort($timeline, function ($a, $b) {
            return strtotime($a['date']) <=> strtotime($b['date']);
        });

        return [
            'id' => $sample->id,
            'slug' => $sample->slug,
            'sample_name' => $sample->wss_name ?? $sample->slug,
            'water_scheme_name' => $sample->waterScheme?->name ?? 'N/A',
            'analyzed_at' => $latestTest?->analyzed_at ?? $sample->created_at,
            'created_at' => $sample->created_at,
            'current_round' => $latestTest?->round ?? 0,
            'status' => $status,
            'status_label' => strtoupper(str_replace('_', ' ', $status)),
            'status_badge' => $statusBadge,
            'cause' => 'Lab Test',
            'unfit_parameters' => 'See Details',
            'sla_text' => $slaText,
            'sla_badge' => $slaBadge,
            'timeline' => $timeline,
        ];
    }
}
