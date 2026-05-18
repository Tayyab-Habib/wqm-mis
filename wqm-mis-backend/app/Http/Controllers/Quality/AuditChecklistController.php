<?php

namespace App\Http\Controllers\Quality;

use App\Http\Controllers\Controller;
use App\Models\AuditChecklistItem;
use App\Models\AuditInspection;
use App\Models\AuditInspectionAnswer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Audit / SOP Inspection Checklist — KPI-008 data source.
 *
 * Two halves:
 *   1) Master checklist items (admin only).
 *   2) Inspections recorded per lab visit, with one answer per active item.
 *
 * KPI rolls up via the per-inspection score accessor on AuditInspection.
 */
class AuditChecklistController extends Controller
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
        if (!$u->isUnscoped() && !$u->can('view_audit_inspections')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        return null;
    }

    private function gateWrite(): ?JsonResponse
    {
        $u = auth()->user();
        if (!$u) return response()->json(['message' => 'Unauthenticated.'], 401);
        if (!$u->isUnscoped() && !$u->can('manage_audit_inspections')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        return null;
    }

    // ── Checklist items ─────────────────────────────────────────────────────

    public function items(Request $request): JsonResponse
    {
        if ($r = $this->gateRead()) return $r;

        $items = AuditChecklistItem::query()
            ->when($request->boolean('only_active', false), fn($q) => $q->where('is_active', true))
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return response()->json(['data' => $items], SymfonyResponse::HTTP_OK);
    }

    public function storeItem(Request $request): JsonResponse
    {
        if ($r = $this->gateWrite()) return $r;

        $validated = $request->validate([
            'question'  => ['required', 'string', 'max:500'],
            'category'  => ['nullable', 'string', 'max:100'],
            'position'  => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $item = AuditChecklistItem::create([
            'question'  => $validated['question'],
            'category'  => $validated['category'] ?? null,
            'position'  => $validated['position'] ?? (AuditChecklistItem::max('position') + 1),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json(['data' => $item], SymfonyResponse::HTTP_CREATED);
    }

    public function updateItem(Request $request, int $id): JsonResponse
    {
        if ($r = $this->gateWrite()) return $r;
        $item = AuditChecklistItem::findOrFail($id);

        $validated = $request->validate([
            'question'  => ['sometimes', 'string', 'max:500'],
            'category'  => ['sometimes', 'nullable', 'string', 'max:100'],
            'position'  => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $item->update($validated);
        return response()->json(['data' => $item], SymfonyResponse::HTTP_OK);
    }

    public function destroyItem(int $id): JsonResponse
    {
        if ($r = $this->gateWrite()) return $r;
        // Soft-delete to preserve historical answers' FK integrity.
        AuditChecklistItem::findOrFail($id)->delete();
        return response()->json(['message' => 'Checklist item removed.'], SymfonyResponse::HTTP_OK);
    }

    // ── Inspections ─────────────────────────────────────────────────────────

    public function inspections(Request $request): JsonResponse
    {
        if ($r = $this->gateRead()) return $r;

        $validated = $request->validate([
            'laboratory_id' => ['nullable', 'integer', 'exists:laboratories,id'],
            'from'          => ['nullable', 'date'],
            'to'            => ['nullable', 'date'],
        ]);

        $scoped = $this->userLabIds();

        $rows = AuditInspection::query()
            ->with(['laboratory:id,name', 'inspector:id,name', 'answers'])
            ->when($scoped !== null, fn($q) => $q->whereIn('laboratory_id', $scoped))
            ->when(!empty($validated['laboratory_id']), fn($q) => $q->where('laboratory_id', $validated['laboratory_id']))
            ->when(!empty($validated['from']), fn($q) => $q->whereDate('inspection_date', '>=', $validated['from']))
            ->when(!empty($validated['to']),   fn($q) => $q->whereDate('inspection_date', '<=', $validated['to']))
            ->orderByDesc('inspection_date')
            ->limit(500)
            ->get()
            ->map(fn(AuditInspection $i) => [
                'id'              => $i->id,
                'laboratory_id'   => $i->laboratory_id,
                'laboratory'      => $i->laboratory?->name,
                'inspector'       => $i->inspector?->name,
                'inspection_date' => $i->inspection_date?->toDateString(),
                'status'          => $i->status,
                'score_pct'       => $i->score_pct,
                'pass_count'      => $i->answers->where('answer', 'pass')->count(),
                'fail_count'      => $i->answers->where('answer', 'fail')->count(),
                'na_count'        => $i->answers->where('answer', 'na')->count(),
                'notes'           => $i->notes,
            ]);

        return response()->json(['data' => $rows], SymfonyResponse::HTTP_OK);
    }

    public function showInspection(int $id): JsonResponse
    {
        if ($r = $this->gateRead()) return $r;

        $i = AuditInspection::with([
            'laboratory:id,name',
            'inspector:id,name',
            'answers.item:id,question,category,position',
        ])->findOrFail($id);

        $scoped = $this->userLabIds();
        if ($scoped !== null && !in_array($i->laboratory_id, $scoped, true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json([
            'data' => [
                'id'              => $i->id,
                'laboratory'      => $i->laboratory?->name,
                'inspector'       => $i->inspector?->name,
                'inspection_date' => $i->inspection_date?->toDateString(),
                'status'          => $i->status,
                'score_pct'       => $i->score_pct,
                'notes'           => $i->notes,
                'evidence_file'   => $i->evidence_file,
                'answers'         => $i->answers->sortBy(fn($a) => $a->item?->position)->values()->map(fn($a) => [
                    'item_id'  => $a->audit_checklist_item_id,
                    'question' => $a->item?->question,
                    'category' => $a->item?->category,
                    'answer'   => $a->answer,
                    'notes'    => $a->notes,
                ]),
            ],
        ], SymfonyResponse::HTTP_OK);
    }

    public function storeInspection(Request $request): JsonResponse
    {
        if ($r = $this->gateWrite()) return $r;

        $validated = $request->validate([
            'laboratory_id'    => ['required', 'integer', 'exists:laboratories,id'],
            'inspector_id'     => ['nullable', 'integer', 'exists:users,id'],
            'inspection_date'  => ['required', 'date', 'before_or_equal:today'],
            'notes'            => ['nullable', 'string', 'max:2000'],
            'evidence_file'    => ['nullable', 'string', 'max:500'],
            'answers'          => ['required', 'array', 'min:1'],
            'answers.*.item_id'=> ['required', 'integer', 'exists:audit_checklist_items,id'],
            'answers.*.answer' => ['required', 'in:pass,fail,na'],
            'answers.*.notes'  => ['nullable', 'string', 'max:500'],
        ]);

        DB::beginTransaction();
        try {
            $inspection = AuditInspection::create([
                'laboratory_id'    => $validated['laboratory_id'],
                'inspector_id'     => $validated['inspector_id'] ?? null,
                'inspection_date'  => $validated['inspection_date'],
                'status'           => 'submitted',
                'notes'            => $validated['notes'] ?? null,
                'evidence_file'    => $validated['evidence_file'] ?? null,
                'created_by'       => auth()->id(),
            ]);

            foreach ($validated['answers'] as $a) {
                AuditInspectionAnswer::create([
                    'audit_inspection_id'     => $inspection->id,
                    'audit_checklist_item_id' => $a['item_id'],
                    'answer'                  => $a['answer'],
                    'notes'                   => $a['notes'] ?? null,
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json([
            'message' => 'Inspection recorded.',
            'data'    => ['id' => $inspection->id, 'score_pct' => $inspection->fresh('answers')->score_pct],
        ], SymfonyResponse::HTTP_CREATED);
    }

    public function destroyInspection(int $id): JsonResponse
    {
        if ($r = $this->gateWrite()) return $r;
        AuditInspection::findOrFail($id)->delete();
        return response()->json(['message' => 'Inspection deleted.'], SymfonyResponse::HTTP_OK);
    }
}
