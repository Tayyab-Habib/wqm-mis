<?php

namespace App\Http\Controllers\Xen;

use App\Http\Controllers\Controller;
use App\Models\PhedDivision;
use App\Models\User;
use App\Models\WaterScheme;
use App\Models\WaterSamples\WaterSample;
use App\Models\WaterSamples\WaterSampleTest;
use App\Models\WaterSamples\WaterSampleDetail;
use App\Enums\WaterSampleTestResultEnum;
use App\Enums\WaterSampleTestStatusEnum;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as Http;

class XenPortalController extends Controller
{
    /**
     * 403 helper — unscoped admins bypass; everyone else must hold $perm.
     * Mirrors XenDashboardController + Secretary/CE portal controllers.
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

    private function scopedUser(): User
    {
        return auth()->user()->loadMissing(['phedDivision', 'district', 'circle', 'region']);
    }

    private function divisionId(): ?int
    {
        return auth()->user()->phed_division_id;
    }

    /* ──────────────────────────────────────────────────────────────────
     |  ME  —  identity + scope (used by XEN portal sidebar/topbar)
     |──────────────────────────────────────────────────────────────────*/
    public function me(Request $request)
    {
        if ($r = $this->gate('view_xen_portal')) return $r;
        $user = $this->scopedUser();

        return response()->json([
            'id'      => $user->id,
            'name'    => $user->name,
            'email'   => $user->email,
            'phone'   => $user->phone,
            'role'    => $user->roles->first()?->name,
            'phed_division' => $user->phedDivision ? [
                'id' => $user->phedDivision->id,
                'name' => $user->phedDivision->name,
            ] : null,
            'district' => $user->district ? [
                'id' => $user->district->id,
                'name' => $user->district->name,
            ] : null,
            'circle'  => $user->circle ? ['id' => $user->circle->id, 'name' => $user->circle->name] : null,
            'region'  => $user->region ? ['id' => $user->region->id, 'name' => $user->region->name] : null,
            'sub_area' => $user->phedDivision?->name,
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  WSS REGISTER  —  schemes in this PHED division with history
     |──────────────────────────────────────────────────────────────────*/
    public function wssRegister(Request $request)
    {
        if ($r = $this->gate('view_xen_wss_register')) return $r;
        $divisionId = $this->divisionId();
        $search     = trim((string) $request->query('q', ''));
        $resultFilter = $request->query('result');

        $schemes = WaterScheme::query()
            ->when($divisionId, fn ($q) => $q->where('phed_division_id', $divisionId))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('name', 'like', "%{$search}%")
                       ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->with(['phedDivision:id,name', 'district:id,name'])
            ->withCount('waterSamples as times_tested')
            ->get()
            ->map(function ($scheme) use ($resultFilter) {
                $lastSample = WaterSample::query()
                    ->where('water_scheme_id', $scheme->id)
                    ->orderByDesc('sampled_at')
                    ->first();

                $lastTest = $lastSample
                    ? WaterSampleTest::where('water_sample_id', $lastSample->id)->orderByDesc('round')->first()
                    : null;

                $lastResult = $lastTest?->result?->value ?? $lastSample?->result;
                $lastResultLabel = match ($lastResult) {
                    WaterSampleTestResultEnum::FIT->value, '1', 1, 'Fit'   => 'Fit',
                    WaterSampleTestResultEnum::UNFIT->value, '2', 2, 'Unfit' => 'Unfit',
                    default => '—',
                };

                if ($resultFilter && strtolower($resultFilter) !== 'all' && strtolower($lastResultLabel) !== strtolower($resultFilter)) {
                    return null;
                }

                // WaterSample's `sampled_at` accessor returns a pre-formatted
                // string like '08 May, 2026 09:30' which Carbon can't re-parse.
                // Read the raw column to do date math.
                $lastSampledRaw     = $lastSample ? $lastSample->getRawOriginal('sampled_at') : null;
                $lastSampledDisplay = $lastSample?->sampled_at;
                $nextScheduled      = $lastSampledRaw ? Carbon::parse($lastSampledRaw)->addMonths(3) : null;
                $overdue            = $nextScheduled ? $nextScheduled->isPast() : false;

                return [
                    'id'                => $scheme->id,
                    'wss_code'          => $scheme->slug,
                    'wss_name'          => $scheme->name,
                    'source_type'       => $scheme->source_type ?? '—',
                    'power_input'       => $scheme->power_input?->value ?? $scheme->power_input ?? '—',
                    'times_tested'      => $scheme->times_tested ?? 0,
                    'last_result'       => $lastResultLabel,
                    'last_sampled_at'   => $lastSampledDisplay,
                    'next_scheduled'    => $nextScheduled?->toDateString(),
                    'overdue'           => $overdue,
                    'phed_division'     => $scheme->phedDivision?->name ?? '—',
                    'last_sample_id'    => $lastSample?->id,
                    'last_sample_slug'  => $lastSample?->slug,
                ];
            })
            ->filter()
            ->values();

        $stats = [
            'total'   => $schemes->count(),
            'last_fit'   => $schemes->where('last_result', 'Fit')->count(),
            'last_unfit' => $schemes->where('last_result', 'Unfit')->count(),
            'overdue'    => $schemes->where('overdue', true)->count(),
        ];

        return response()->json([
            'stats' => $stats,
            'schemes' => $schemes,
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  GSR  —  Sample-wise results for this division
     |──────────────────────────────────────────────────────────────────*/
    public function gsr(Request $request)
    {
        if ($r = $this->gate('view_xen_gsr')) return $r;
        $divisionId = $this->divisionId();
        $from = $request->query('from');
        $to   = $request->query('to');
        $result = $request->query('result');

        $query = WaterSample::query()
            ->when($divisionId, fn ($q) => $q->where('phed_division_id', $divisionId))
            ->when($from, fn ($q) => $q->whereDate('sampled_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('sampled_at', '<=', $to))
            ->with(['waterScheme:id,name,slug', 'phedDivision:id,name', 'tests' => fn ($q) => $q->orderByDesc('round')]);

        $rows = $query->orderByDesc('sampled_at')->get()->map(function ($s, $i) {
            $latest = $s->tests->first();
            $resultVal = $latest?->result?->value ?? $s->result;
            $resultLabel = match ($resultVal) {
                1, '1', WaterSampleTestResultEnum::FIT->value, 'Fit' => 'Fit',
                2, '2', WaterSampleTestResultEnum::UNFIT->value, 'Unfit' => 'Unfit',
                default => '—',
            };

            return [
                'index'           => $i + 1,
                'id'              => $s->id,
                'slug'            => $s->slug,
                'wss_name'        => $s->waterScheme?->name ?? $s->sample_name ?? '—',
                'water_scheme_id' => $s->water_scheme_id,
                'sampled_at'      => $s->sampled_at,
                'point'           => $s->sampling_point?->value ?? $s->sampling_point ?? '—',
                'phed_division'   => $s->phedDivision?->name ?? '—',
                'type'            => $s->test_type?->value ?? $s->test_type ?? '—',
                'result'          => $resultLabel,
                'cause'           => $resultLabel === 'Unfit' ? 'Lab Test' : '—',
                'parameter'       => $resultLabel === 'Unfit' ? ($latest?->remarks ?: 'See Details') : '—',
            ];
        });

        // Apply result filter post-hoc (so the row's resolved label matches)
        if ($result && strtolower($result) !== 'all') {
            $rows = $rows->filter(fn ($r) => strtolower($r['result']) === strtolower($result))->values();
            $rows = $rows->map(function ($r, $i) {
                $r['index'] = $i + 1;
                return $r;
            });
        }

        $stats = [
            'total' => $rows->count(),
            'fit'   => $rows->where('result', 'Fit')->count(),
            'unfit' => $rows->where('result', 'Unfit')->count(),
        ];
        $stats['percent_unfit'] = $stats['total'] > 0 ? round(($stats['unfit'] / $stats['total']) * 100, 1) : 0;

        return response()->json([
            'stats' => $stats,
            'rows'  => $rows,
            'meta'  => [
                'phed_division' => $this->scopedUser()->phedDivision?->name,
                'from' => $from,
                'to'   => $to,
            ],
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  ISR  —  Individual Sample Report (list + detail)
     |──────────────────────────────────────────────────────────────────*/
    public function isrList(Request $request)
    {
        if ($r = $this->gate('view_xen_isr')) return $r;
        $divisionId = $this->divisionId();
        $search = trim((string) $request->query('q', ''));
        $resultFilter = $request->query('result');
        $date = $request->query('date');

        $rows = WaterSample::query()
            ->when($divisionId, fn ($q) => $q->where('phed_division_id', $divisionId))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('slug', 'like', "%{$search}%")
                       ->orWhereHas('waterScheme', fn ($w) => $w->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($date, fn ($q) => $q->whereDate('sampled_at', $date))
            ->with(['waterScheme:id,name', 'tests' => fn ($q) => $q->orderByDesc('round')])
            ->orderByDesc('sampled_at')
            ->limit(500)
            ->get()
            ->map(function ($s) {
                $latest = $s->tests->first();
                $resultVal = $latest?->result?->value ?? $s->result;
                $resultLabel = match ($resultVal) {
                    1, '1', WaterSampleTestResultEnum::FIT->value, 'Fit' => 'Fit',
                    2, '2', WaterSampleTestResultEnum::UNFIT->value, 'Unfit' => 'Unfit',
                    default => '—',
                };

                return [
                    'id'           => $s->id,
                    'slug'         => $s->slug,
                    'wss_name'     => $s->waterScheme?->name ?? '—',
                    'sampled_at'   => $s->sampled_at,
                    'type'         => $s->test_type?->value ?? $s->test_type ?? '—',
                    'result'       => $resultLabel,
                    'cause_param'  => $resultLabel === 'Unfit'
                        ? trim(($latest?->remarks ?: 'Lab Test') . ' (' . ($s->slug ?? '') . ')')
                        : '—',
                ];
            });

        if ($resultFilter && strtolower($resultFilter) !== 'all') {
            $rows = $rows->filter(fn ($r) => strtolower($r['result']) === strtolower($resultFilter))->values();
        }

        return response()->json([
            'rows'  => $rows,
            'count' => $rows->count(),
        ]);
    }

    public function isrShow(Request $request, $id)
    {
        if ($r = $this->gate('view_xen_isr')) return $r;
        $divisionId = $this->divisionId();

        $sample = WaterSample::with([
                'waterScheme:id,name,slug',
                'phedDivision:id,name',
                'district:id,name',
                'tests' => fn ($q) => $q->orderBy('round'),
                'waterSampleDetails.test:id,water_quality_parameter,unit,who_guideline_start,who_guideline_end',
            ])
            ->when($divisionId, fn ($q) => $q->where('phed_division_id', $divisionId))
            ->findOrFail($id);

        $latest = $sample->tests->sortByDesc('round')->first();
        $resultVal = $latest?->result?->value ?? $sample->result;
        $resultLabel = match ($resultVal) {
            1, '1', WaterSampleTestResultEnum::FIT->value, 'Fit' => 'Fit',
            2, '2', WaterSampleTestResultEnum::UNFIT->value, 'Unfit' => 'Unfit',
            default => '—',
        };

        $parameters = $sample->waterSampleDetails->map(function ($d) {
            return [
                'parameter' => $d->test?->water_quality_parameter ?? '—',
                'unit'      => $d->test?->unit ?? '',
                'value'     => $d->analysis_result ?? $d->input_result ?? '—',
                'range'     => trim(($d->test?->who_guideline_start ?? '') . ' – ' . ($d->test?->who_guideline_end ?? ''), ' –'),
                'round'     => $d->waterSampleTest?->round ?? 0,
            ];
        })->values();

        return response()->json([
            'id'           => $sample->id,
            'slug'         => $sample->slug,
            'wss_name'     => $sample->waterScheme?->name ?? '—',
            'wss_code'     => $sample->waterScheme?->slug ?? '—',
            'sampled_at'   => $sample->sampled_at,
            'analyzed_at'  => $sample->analyzed_at,
            'reported_at'  => $sample->reported_at,
            'type'         => $sample->test_type?->value ?? $sample->test_type ?? '—',
            'sampling_point' => $sample->sampling_point?->value ?? $sample->sampling_point ?? '—',
            'source_type'  => $sample->source_type?->value ?? $sample->source_type ?? '—',
            'result'       => $resultLabel,
            'phed_division'=> $sample->phedDivision?->name ?? '—',
            'district'     => $sample->district?->name ?? '—',
            'remarks'      => $sample->remarks,
            'parameters'   => $parameters,
            'tests'        => $sample->tests->map(fn ($t) => [
                'round' => $t->round,
                'sampled_at' => $t->sampled_at,
                'analyzed_at' => $t->analyzed_at,
                'status' => $t->status?->label() ?? '—',
                'result' => $t->result?->label() ?? '—',
                'remarks' => $t->remarks,
            ])->values(),
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  RETEST SAMPLES  —  list with stats
     |──────────────────────────────────────────────────────────────────*/
    public function retestSamples(Request $request)
    {
        if ($r = $this->gate('view_xen_retest_samples')) return $r;
        $divisionId = $this->divisionId();

        // Sample IDs that have UNFIT tests
        $unfitIds = DB::table('water_sample_tests as wst')
            ->join('water_samples as ws', 'wst.water_sample_id', '=', 'ws.id')
            ->where('wst.result', WaterSampleTestResultEnum::UNFIT->value)
            ->whereNull('ws.deleted_at')
            ->when($divisionId, fn ($q) => $q->where('ws.phed_division_id', $divisionId))
            ->distinct()
            ->pluck('wst.water_sample_id');

        $retests = WaterSampleTest::query()
            ->whereIn('water_sample_id', $unfitIds)
            ->where('round', '>', 0)
            ->with(['waterSample.waterScheme:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($t) {
                $original = WaterSampleTest::where('water_sample_id', $t->water_sample_id)
                    ->where('round', 0)
                    ->first();
                $statusEnum = $t->status;
                $resultEnum = $t->result;

                $statusLabel = $statusEnum?->label() ?? 'Pending';
                $resultLabel = $resultEnum?->label();

                // UI status: Awaiting Analysis / In Analysis / Analysed
                $uiStatus = match (true) {
                    $statusEnum?->value === WaterSampleTestStatusEnum::COMPLETED->value => 'Analysed',
                    $statusEnum?->value === WaterSampleTestStatusEnum::IN_PROGRESS->value => 'In Analysis',
                    default => 'Awaiting Analysis',
                };

                return [
                    'id'             => $t->id,
                    'retest_slug'    => $t->waterSample?->slug ? $t->waterSample->slug : ('R' . $t->id),
                    'original_slug'  => $t->waterSample?->slug,
                    'water_sample_id'=> $t->water_sample_id,
                    'wss_name'       => $t->waterSample?->waterScheme?->name ?? '—',
                    'stage'          => 'R' . $t->round,
                    'sampled_at'     => $t->sampled_at,
                    'cause'          => $original?->remarks ?: 'Lab Test',
                    'status'         => $uiStatus,
                    'result'         => $resultLabel ?? '—',
                ];
            });

        $stats = [
            'awaiting_analysis' => $retests->where('status', 'Awaiting Analysis')->count(),
            'fit_resolved'      => $retests->where('result', 'Fit')->count(),
            'still_unfit'       => $retests->where('result', 'Unfit')->count(),
        ];

        return response()->json([
            'stats'   => $stats,
            'retests' => $retests->values(),
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  OVERDUE WSS  —  schemes past scheduled retest
     |──────────────────────────────────────────────────────────────────*/
    public function overdueWss(Request $request)
    {
        // Bundled under the umbrella perm — overdue-wss is an ambient
        // layout-side call (used for badge counts), not a dedicated screen.
        if ($r = $this->gate('view_xen_portal')) return $r;
        $divisionId = $this->divisionId();

        $rows = WaterScheme::query()
            ->when($divisionId, fn ($q) => $q->where('phed_division_id', $divisionId))
            ->get()
            ->map(function ($scheme) {
                $lastSample = WaterSample::query()
                    ->where('water_scheme_id', $scheme->id)
                    ->orderByDesc('sampled_at')
                    ->first();

                if (!$lastSample || !$lastSample->sampled_at) {
                    return null;
                }
                $next = Carbon::parse($lastSample->sampled_at)->addMonths(3);
                if (!$next->isPast()) return null;

                return [
                    'id'        => $scheme->id,
                    'name'      => $scheme->name,
                    'due_at'    => $next->toDateString(),
                    'overdue_days' => abs($next->diffInDays(now())),
                ];
            })
            ->filter()
            ->sortByDesc('overdue_days')
            ->values();

        return response()->json([
            'rows'  => $rows,
            'count' => $rows->count(),
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  NOTIFICATIONS  —  user's recent notifications
     |──────────────────────────────────────────────────────────────────*/
    public function notifications(Request $request)
    {
        // Bundled under the umbrella perm — notifications are an ambient
        // layout-side call (used for badge counts and the bell icon).
        if ($r = $this->gate('view_xen_portal')) return $r;
        $user = $this->scopedUser();

        $rows = DB::table('notifications')
            ->where('notifiable_id', $user->id)
            ->where('notifiable_type', User::class)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $items = $rows->map(function ($n) {
            $data = is_string($n->data ?? '') ? json_decode($n->data, true) : ($n->data ?? []);
            $sample = $n->water_sample_id ? WaterSample::find($n->water_sample_id) : null;

            $kind = match ($n->type_key) {
                'SAMPLE_UNFIT'      => 'Unfit',
                'RETEST_REQUESTED'  => 'Retest',
                'ESCALATION'        => 'Escalation',
                default             => 'Update',
            };

            return [
                'id'         => $n->id,
                'sample_slug'=> $sample?->slug ?? ($data['sample_slug'] ?? null),
                'sample_id'  => $n->water_sample_id ?? ($data['sample_id'] ?? null),
                'kind'       => $kind,
                'created_at' => $n->created_at,
                'due_at'     => $n->due_at,
                'read_at'    => $n->read_at,
                'message'    => $data['message'] ?? null,
            ];
        });

        // Unread count = rows with read_at NULL. The full items list still
        // returns read items too so the dropdown can show history.
        $unreadCount = $rows->whereNull('read_at')->count();

        return response()->json([
            'items'         => $items,
            'count'         => $unreadCount,
            'total'         => $items->count(),
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  SETTINGS  —  update XEN officer details
     |──────────────────────────────────────────────────────────────────*/
    public function updateSettings(Request $request)
    {
        // WRITE — gated on update_xen_settings (separate from
        // view_xen_settings so admins can grant read-only settings view
        // without letting the user mutate them).
        if ($r = $this->gate('update_xen_settings')) return $r;
        $request->validate([
            'name'  => 'required|string|max:120',
            'phone' => 'nullable|string|max:30',
            'transfer_change_date' => 'nullable|date',
            'remarks' => 'nullable|string|max:500',
            'district'  => 'nullable|string|max:120',
            'sub_area'  => 'nullable|string|max:255',
        ]);

        $user = $this->scopedUser();

        $changes = [];
        if ($request->filled('name') && $user->name !== $request->name) {
            $changes[] = ['field' => 'XEN Full Name', 'old' => $user->name, 'new' => $request->name];
            $user->name = $request->name;
        }
        if ($request->filled('phone') && $user->phone !== $request->phone) {
            $changes[] = ['field' => 'Phone', 'old' => $user->phone, 'new' => $request->phone];
            $user->phone = $request->phone;
        }
        $user->save();

        return response()->json([
            'message' => 'Settings updated successfully',
            'changes' => $changes,
            'user'    => [
                'name' => $user->name,
                'phone' => $user->phone,
            ],
        ]);
    }
}
