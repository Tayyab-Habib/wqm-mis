<?php

namespace App\Http\Controllers\Quality;

use App\Http\Controllers\Controller;
use App\Models\Laboratories\Laboratory;
use App\Models\PtRound;
use App\Models\PtRoundItem;
use App\Models\PtRoundParticipant;
use App\Models\PtRoundResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Proficiency Testing — KPI-001 data source.
 *
 * Admin (manage_pt_rounds):
 *   - Create rounds with one or more parameter items (test + reference value
 *     + tolerance %).
 *   - Pick which labs participate.
 *   - Close the round when results are in.
 *
 * Lab-incharge / lab-assistant (submit_pt_results):
 *   - See their lab's pending PT rounds, submit one reading per item.
 *   - System auto-computes |deviation|/ref and pass/fail vs tolerance.
 *
 * KPI roll-up per lab per period = passed_results / submitted_results × 100.
 * Computed in DashboardController::labKpis().
 */
class PtRoundController extends Controller
{
    private function userLabIds(): ?array
    {
        $u = auth()->user();
        if (!$u) return [0];
        if ($u->isUnscoped()) return null;
        if (!$u->hasAnyRole(['lab-incharge', 'laboratory-assistant', 'junior-clerk'])) {
            return null;
        }
        $ids = $u->laboratories()->pluck('laboratories.id')->all();
        return empty($ids) ? [0] : $ids;
    }

    private function gateRead(): ?JsonResponse
    {
        $u = auth()->user();
        if (!$u) return response()->json(['message' => 'Unauthenticated.'], 401);
        if (!$u->isUnscoped() && !$u->can('view_pt_rounds')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        return null;
    }

    private function gateAdminWrite(): ?JsonResponse
    {
        $u = auth()->user();
        if (!$u) return response()->json(['message' => 'Unauthenticated.'], 401);
        if (!$u->isUnscoped() && !$u->can('manage_pt_rounds')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        return null;
    }

    private function gateLabSubmit(int $labId): ?JsonResponse
    {
        $u = auth()->user();
        if (!$u) return response()->json(['message' => 'Unauthenticated.'], 401);
        if (!$u->isUnscoped() && !$u->can('submit_pt_results')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        $scoped = $this->userLabIds();
        if ($scoped !== null && !in_array($labId, $scoped, true)) {
            return response()->json(['message' => 'Cannot submit for a lab outside your scope.'], 403);
        }
        return null;
    }

    // ── Rounds ──────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        if ($r = $this->gateRead()) return $r;

        $scoped = $this->userLabIds();

        $rounds = PtRound::query()
            ->with(['items.test:id,water_quality_parameter,unit', 'participants.laboratory:id,name', 'creator:id,name'])
            // Lab-scoped users only see rounds their lab participates in.
            ->when($scoped !== null, function ($q) use ($scoped) {
                $q->whereHas('participants', fn($pq) => $pq->whereIn('laboratory_id', $scoped));
            })
            ->orderByDesc('round_date')
            ->limit(200)
            ->get()
            ->map(fn(PtRound $r) => [
                'id'         => $r->id,
                'code'       => $r->code,
                'name'       => $r->name,
                'round_date' => $r->round_date?->toDateString(),
                'due_date'   => $r->due_date?->toDateString(),
                'status'     => $r->status,
                'items'      => $r->items->map(fn($i) => [
                    'id'              => $i->id,
                    'test_id'         => $i->test_id,
                    'parameter'       => $i->test?->water_quality_parameter,
                    'reference_value' => (float) $i->reference_value,
                    'tolerance_pct'   => (float) $i->tolerance_pct,
                    'unit'            => $i->unit ?: $i->test?->unit,
                ]),
                'participants' => $r->participants->map(fn($p) => [
                    'id'             => $p->id,
                    'laboratory_id'  => $p->laboratory_id,
                    'laboratory'     => $p->laboratory?->name,
                    'status'         => $p->status,
                    'submitted_at'   => $p->submitted_at?->toIso8601String(),
                ]),
                'created_by' => $r->creator?->name,
            ]);

        return response()->json(['data' => $rounds], SymfonyResponse::HTTP_OK);
    }

    public function show(int $id): JsonResponse
    {
        if ($r = $this->gateRead()) return $r;

        $round = PtRound::with([
            'items.test:id,water_quality_parameter,unit,permissible_limits',
            'participants.laboratory:id,name',
            'participants.results',
        ])->findOrFail($id);

        return response()->json([
            'data' => [
                'id'         => $round->id,
                'code'       => $round->code,
                'name'       => $round->name,
                'round_date' => $round->round_date?->toDateString(),
                'due_date'   => $round->due_date?->toDateString(),
                'status'     => $round->status,
                'notes'      => $round->notes,
                'items'      => $round->items->map(fn($i) => [
                    'id'              => $i->id,
                    'parameter'       => $i->test?->water_quality_parameter,
                    'reference_value' => (float) $i->reference_value,
                    'tolerance_pct'   => (float) $i->tolerance_pct,
                    'unit'            => $i->unit ?: $i->test?->unit,
                ]),
                'participants' => $round->participants->map(function ($p) use ($round) {
                    $results = $p->results->keyBy('pt_round_item_id');
                    return [
                        'id'             => $p->id,
                        'laboratory_id'  => $p->laboratory_id,
                        'laboratory'     => $p->laboratory?->name,
                        'status'         => $p->status,
                        'submitted_at'   => $p->submitted_at?->toIso8601String(),
                        'results'        => $round->items->map(fn($i) => [
                            'item_id'         => $i->id,
                            'parameter'       => $i->test?->water_quality_parameter,
                            'submitted_value' => $results->get($i->id)?->submitted_value !== null ? (float) $results->get($i->id)->submitted_value : null,
                            'deviation_pct'   => $results->get($i->id)?->deviation_pct  !== null ? (float) $results->get($i->id)->deviation_pct  : null,
                            'passed'          => $results->get($i->id)?->passed,
                        ]),
                    ];
                }),
            ],
        ], SymfonyResponse::HTTP_OK);
    }

    public function store(Request $request): JsonResponse
    {
        if ($r = $this->gateAdminWrite()) return $r;

        $validated = $request->validate([
            'code'                       => ['required', 'string', 'max:32', 'unique:pt_rounds,code'],
            'name'                       => ['required', 'string', 'max:255'],
            'round_date'                 => ['required', 'date'],
            'due_date'                   => ['required', 'date', 'after_or_equal:round_date'],
            'notes'                      => ['nullable', 'string', 'max:2000'],
            'items'                      => ['required', 'array', 'min:1'],
            'items.*.test_id'            => ['required', 'integer', 'exists:tests,id'],
            'items.*.reference_value'    => ['required', 'numeric', 'min:0'],
            'items.*.tolerance_pct'      => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.unit'               => ['nullable', 'string', 'max:32'],
            'participant_lab_ids'        => ['required', 'array', 'min:1'],
            'participant_lab_ids.*'      => ['integer', 'exists:laboratories,id'],
        ]);

        DB::beginTransaction();
        try {
            $round = PtRound::create([
                'code'       => $validated['code'],
                'name'       => $validated['name'],
                'round_date' => $validated['round_date'],
                'due_date'   => $validated['due_date'],
                'status'     => 'open',
                'notes'      => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['items'] as $i) {
                PtRoundItem::create([
                    'pt_round_id'     => $round->id,
                    'test_id'         => $i['test_id'],
                    'reference_value' => $i['reference_value'],
                    'tolerance_pct'   => $i['tolerance_pct'] ?? 15.0,
                    'unit'            => $i['unit'] ?? null,
                ]);
            }

            foreach (array_unique($validated['participant_lab_ids']) as $labId) {
                PtRoundParticipant::create([
                    'pt_round_id'   => $round->id,
                    'laboratory_id' => $labId,
                    'status'        => 'pending',
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json([
            'message' => 'PT round created.',
            'data'    => ['id' => $round->id],
        ], SymfonyResponse::HTTP_CREATED);
    }

    public function close(int $id): JsonResponse
    {
        if ($r = $this->gateAdminWrite()) return $r;
        $round = PtRound::findOrFail($id);
        $round->update(['status' => 'closed']);
        return response()->json(['message' => 'Round closed.'], SymfonyResponse::HTTP_OK);
    }

    public function destroy(int $id): JsonResponse
    {
        if ($r = $this->gateAdminWrite()) return $r;
        PtRound::findOrFail($id)->delete();
        return response()->json(['message' => 'Round deleted.'], SymfonyResponse::HTTP_OK);
    }

    /**
     * POST /api/quality/pt-rounds/{roundId}/submit/{labId}
     *
     * Lab submits one reading per item. Pass/fail is auto-computed:
     *   deviation_pct = |submitted - ref| / ref × 100
     *   passed = deviation_pct <= tolerance_pct
     *
     * Idempotent — re-submitting overwrites existing results for the same
     * (participant, item) pair.
     */
    public function submit(Request $request, int $roundId, int $labId): JsonResponse
    {
        if ($r = $this->gateLabSubmit($labId)) return $r;

        $validated = $request->validate([
            'results'                  => ['required', 'array', 'min:1'],
            'results.*.item_id'        => ['required', 'integer', 'exists:pt_round_items,id'],
            'results.*.submitted_value'=> ['required', 'numeric'],
            'results.*.notes'          => ['nullable', 'string', 'max:500'],
            'notes'                    => ['nullable', 'string', 'max:1000'],
        ]);

        $round = PtRound::findOrFail($roundId);
        if ($round->status !== 'open') {
            return response()->json(['message' => 'Round is not open for submissions.'], SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $participant = PtRoundParticipant::where('pt_round_id', $roundId)
            ->where('laboratory_id', $labId)
            ->firstOrFail();

        $items = PtRoundItem::where('pt_round_id', $roundId)->get()->keyBy('id');

        DB::beginTransaction();
        try {
            foreach ($validated['results'] as $row) {
                $item = $items->get($row['item_id']);
                if (!$item || $item->pt_round_id !== $roundId) continue; // ignore unknown items

                $ref = (float) $item->reference_value;
                $val = (float) $row['submitted_value'];
                $deviation = $ref > 0 ? abs($val - $ref) / $ref * 100 : null;
                $passed = $deviation !== null && $deviation <= (float) $item->tolerance_pct;

                PtRoundResult::updateOrCreate(
                    [
                        'pt_round_participant_id' => $participant->id,
                        'pt_round_item_id'        => $item->id,
                    ],
                    [
                        'submitted_value' => $val,
                        'deviation_pct'   => $deviation,
                        'passed'          => $passed,
                        'notes'           => $row['notes'] ?? null,
                    ]
                );
            }

            $participant->update([
                'status'       => 'submitted',
                'submitted_at' => now(),
                'submitted_by' => auth()->id(),
                'notes'        => $validated['notes'] ?? $participant->notes,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json([
            'message' => 'Results submitted.',
            'data'    => [
                'participant_id' => $participant->id,
                'submitted_at'   => $participant->submitted_at?->toIso8601String(),
            ],
        ], SymfonyResponse::HTTP_OK);
    }
}
