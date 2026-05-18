<?php

namespace App\Http\Controllers\Quality;

use App\Http\Controllers\Controller;
use App\Models\VerificationVisit;
use App\Models\VerificationVisitSample;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Verification Visit Log — KPI-009 data source.
 *
 * One row per technical-head visit. Aggregate counts are stored on the
 * visit row (samples_verified / samples_matched); per-sample detail is
 * optional via verification_visit_samples (used when admin wants to
 * record which specific samples were mis-classified).
 *
 * Writes are admin-only (no "technical-head" role exists yet). Reads are
 * broad (oversight roles + lab-incharge see their own).
 */
class VerificationVisitController extends Controller
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
        if (!$u->isUnscoped() && !$u->can('view_verification_visits')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        return null;
    }

    private function gateWrite(): ?JsonResponse
    {
        $u = auth()->user();
        if (!$u) return response()->json(['message' => 'Unauthenticated.'], 401);
        if (!$u->isUnscoped() && !$u->can('manage_verification_visits')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        return null;
    }

    public function index(Request $request): JsonResponse
    {
        if ($r = $this->gateRead()) return $r;

        $validated = $request->validate([
            'laboratory_id' => ['nullable', 'integer', 'exists:laboratories,id'],
            'from'          => ['nullable', 'date'],
            'to'            => ['nullable', 'date'],
        ]);

        $scoped = $this->userLabIds();

        $rows = VerificationVisit::query()
            ->with(['laboratory:id,name', 'technicalHead:id,name', 'creator:id,name'])
            ->when($scoped !== null, fn($q) => $q->whereIn('laboratory_id', $scoped))
            ->when(!empty($validated['laboratory_id']), fn($q) => $q->where('laboratory_id', $validated['laboratory_id']))
            ->when(!empty($validated['from']), fn($q) => $q->whereDate('visit_date', '>=', $validated['from']))
            ->when(!empty($validated['to']),   fn($q) => $q->whereDate('visit_date', '<=', $validated['to']))
            ->orderByDesc('visit_date')
            ->limit(500)
            ->get()
            ->map(fn(VerificationVisit $v) => [
                'id'               => $v->id,
                'laboratory_id'    => $v->laboratory_id,
                'laboratory'       => $v->laboratory?->name,
                'technical_head'   => $v->technicalHead?->name,
                'visit_date'       => $v->visit_date?->toDateString(),
                'samples_verified' => $v->samples_verified,
                'samples_matched'  => $v->samples_matched,
                'match_rate'       => $v->match_rate,
                'observations'     => $v->observations,
                'evidence_file'    => $v->evidence_file,
                'created_by'       => $v->creator?->name,
                'created_at'       => $v->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $rows], SymfonyResponse::HTTP_OK);
    }

    public function show(int $id): JsonResponse
    {
        if ($r = $this->gateRead()) return $r;

        $v = VerificationVisit::with([
            'laboratory:id,name',
            'technicalHead:id,name',
            'samples.waterSample:id,slug,sample_name,result',
        ])->findOrFail($id);

        $scoped = $this->userLabIds();
        if ($scoped !== null && !in_array($v->laboratory_id, $scoped, true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json([
            'data' => [
                'id'               => $v->id,
                'laboratory'       => $v->laboratory?->name,
                'technical_head'   => $v->technicalHead?->name,
                'visit_date'       => $v->visit_date?->toDateString(),
                'samples_verified' => $v->samples_verified,
                'samples_matched'  => $v->samples_matched,
                'match_rate'       => $v->match_rate,
                'observations'     => $v->observations,
                'evidence_file'    => $v->evidence_file,
                'samples'          => $v->samples->map(fn($s) => [
                    'id'             => $s->id,
                    'water_sample_id'=> $s->water_sample_id,
                    'sample_slug'    => $s->sample_slug ?: $s->waterSample?->slug,
                    'sample_name'    => $s->waterSample?->sample_name,
                    'lab_result'     => $s->waterSample?->result,
                    'matched'        => $s->matched,
                    'notes'          => $s->notes,
                ]),
            ],
        ], SymfonyResponse::HTTP_OK);
    }

    public function store(Request $request): JsonResponse
    {
        if ($r = $this->gateWrite()) return $r;

        $validated = $request->validate([
            'laboratory_id'     => ['required', 'integer', 'exists:laboratories,id'],
            'technical_head_id' => ['nullable', 'integer', 'exists:users,id'],
            'visit_date'        => ['required', 'date', 'before_or_equal:today'],
            'samples_verified'  => ['required', 'integer', 'min:0'],
            'samples_matched'   => ['required', 'integer', 'min:0'],
            'observations'      => ['nullable', 'string', 'max:2000'],
            'evidence_file'     => ['nullable', 'string', 'max:500'],
            'sample_details'    => ['nullable', 'array'],
            'sample_details.*.water_sample_id' => ['nullable', 'integer', 'exists:water_samples,id'],
            'sample_details.*.sample_slug'     => ['nullable', 'string', 'max:64'],
            'sample_details.*.matched'         => ['required_with:sample_details', 'boolean'],
            'sample_details.*.notes'           => ['nullable', 'string', 'max:500'],
        ]);

        if ($validated['samples_matched'] > $validated['samples_verified']) {
            return response()->json([
                'message' => 'Samples matched cannot exceed samples verified.',
                'errors'  => ['samples_matched' => ['Matched cannot exceed verified.']],
            ], SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::beginTransaction();
        try {
            $visit = VerificationVisit::create([
                'laboratory_id'     => $validated['laboratory_id'],
                'technical_head_id' => $validated['technical_head_id'] ?? null,
                'visit_date'        => $validated['visit_date'],
                'samples_verified'  => $validated['samples_verified'],
                'samples_matched'   => $validated['samples_matched'],
                'observations'      => $validated['observations'] ?? null,
                'evidence_file'     => $validated['evidence_file'] ?? null,
                'created_by'        => auth()->id(),
            ]);

            foreach ($validated['sample_details'] ?? [] as $d) {
                VerificationVisitSample::create([
                    'verification_visit_id' => $visit->id,
                    'water_sample_id'       => $d['water_sample_id'] ?? null,
                    'sample_slug'           => $d['sample_slug']     ?? null,
                    'matched'               => (bool) $d['matched'],
                    'notes'                 => $d['notes'] ?? null,
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json([
            'message' => 'Verification visit recorded.',
            'data'    => ['id' => $visit->id],
        ], SymfonyResponse::HTTP_CREATED);
    }

    public function destroy(int $id): JsonResponse
    {
        if ($r = $this->gateWrite()) return $r;
        $visit = VerificationVisit::findOrFail($id);
        $visit->delete();
        return response()->json(['message' => 'Verification visit deleted.'], SymfonyResponse::HTTP_OK);
    }
}
