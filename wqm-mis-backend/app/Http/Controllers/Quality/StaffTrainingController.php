<?php

namespace App\Http\Controllers\Quality;

use App\Http\Controllers\Controller;
use App\Models\Laboratories\Laboratory;
use App\Models\StaffTraining;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Training Register — KPI-007 data source.
 *
 * Lab-scope enforcement:
 *   - System / view-only roles see every lab.
 *   - lab-incharge / lab-assistant / junior-clerk see only their lab(s),
 *     and writes are constrained to those labs.
 *
 * Validity defaults to training_date + 12 months — overridable on input
 * for trainings with a different certification window (e.g. ISO refresher
 * cycles can be 3 years).
 */
class StaffTrainingController extends Controller
{
    /** Lab IDs the current user is attached to (lab-scoped roles); null = no filter. */
    private function userLabIds(): ?array
    {
        $u = auth()->user();
        if (!$u) return [0];                          // unauthenticated → match nothing
        if ($u->isUnscoped()) return null;            // SA / manager / view-only / general-view
        // Only lab-tier roles get pivot scoping. Hierarchy roles fall back to unscoped read.
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
        if (!$u->isUnscoped() && !$u->can('view_staff_trainings')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        return null;
    }

    private function gateWrite(int $labId): ?JsonResponse
    {
        $u = auth()->user();
        if (!$u) return response()->json(['message' => 'Unauthenticated.'], 401);
        if (!$u->isUnscoped() && !$u->can('manage_staff_trainings')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        // Lab-scoped writers can only write to their own lab(s).
        $scoped = $this->userLabIds();
        if ($scoped !== null && !in_array($labId, $scoped, true)) {
            return response()->json(['message' => 'Cannot write to a lab outside your scope.'], 403);
        }
        return null;
    }

    /**
     * GET /api/quality/staff-trainings?laboratory_id=&from=&to=
     * Recent trainings, newest first, capped at 500 rows.
     */
    public function index(Request $request): JsonResponse
    {
        if ($r = $this->gateRead()) return $r;

        $validated = $request->validate([
            'laboratory_id' => ['nullable', 'integer', 'exists:laboratories,id'],
            'from'          => ['nullable', 'date'],
            'to'            => ['nullable', 'date'],
        ]);

        $scoped = $this->userLabIds();

        $rows = StaffTraining::query()
            ->with(['laboratory:id,name', 'staff:id,name', 'creator:id,name'])
            ->when($scoped !== null, fn($q) => $q->whereIn('laboratory_id', $scoped))
            ->when(!empty($validated['laboratory_id']), fn($q) => $q->where('laboratory_id', $validated['laboratory_id']))
            ->when(!empty($validated['from']), fn($q) => $q->whereDate('training_date', '>=', $validated['from']))
            ->when(!empty($validated['to']),   fn($q) => $q->whereDate('training_date', '<=', $validated['to']))
            ->orderByDesc('training_date')
            ->limit(500)
            ->get()
            ->map(fn(StaffTraining $t) => [
                'id'             => $t->id,
                'laboratory_id'  => $t->laboratory_id,
                'laboratory'     => $t->laboratory?->name,
                'user_id'        => $t->user_id,
                'staff_name'     => $t->staff_name,
                'training_topic' => $t->training_topic,
                'training_date'  => $t->training_date?->toDateString(),
                'valid_until'    => $t->valid_until?->toDateString(),
                'is_valid'       => $t->valid_until && $t->valid_until->isFuture(),
                'evidence_file'  => $t->evidence_file,
                'notes'          => $t->notes,
                'created_by'     => $t->creator?->name,
                'created_at'     => $t->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $rows], SymfonyResponse::HTTP_OK);
    }

    /**
     * POST /api/quality/staff-trainings
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'laboratory_id'  => ['required', 'integer', 'exists:laboratories,id'],
            'user_id'        => ['nullable', 'integer', 'exists:users,id'],
            'staff_name'     => ['required', 'string', 'max:255'],
            'training_topic' => ['required', 'string', 'max:255'],
            'training_date'  => ['required', 'date', 'before_or_equal:today'],
            'valid_until'    => ['nullable', 'date', 'after:training_date'],
            'evidence_file'  => ['nullable', 'string', 'max:500'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ]);

        if ($r = $this->gateWrite($validated['laboratory_id'])) return $r;

        // Default validity = training_date + 12 months when not supplied.
        $validUntil = !empty($validated['valid_until'])
            ? Carbon::parse($validated['valid_until'])
            : Carbon::parse($validated['training_date'])->addMonths(12);

        $row = StaffTraining::create([
            'laboratory_id'  => $validated['laboratory_id'],
            'user_id'        => $validated['user_id'] ?? null,
            'staff_name'     => $validated['staff_name'],
            'training_topic' => $validated['training_topic'],
            'training_date'  => $validated['training_date'],
            'valid_until'    => $validUntil,
            'evidence_file'  => $validated['evidence_file'] ?? null,
            'notes'          => $validated['notes'] ?? null,
            'created_by'     => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Training record saved.',
            'data'    => ['id' => $row->id],
        ], SymfonyResponse::HTTP_CREATED);
    }

    /**
     * PUT /api/quality/staff-trainings/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $training = StaffTraining::findOrFail($id);
        if ($r = $this->gateWrite($training->laboratory_id)) return $r;

        $validated = $request->validate([
            'staff_name'     => ['sometimes', 'string', 'max:255'],
            'training_topic' => ['sometimes', 'string', 'max:255'],
            'training_date'  => ['sometimes', 'date', 'before_or_equal:today'],
            'valid_until'    => ['sometimes', 'date'],
            'evidence_file'  => ['sometimes', 'nullable', 'string', 'max:500'],
            'notes'          => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $training->update($validated);

        return response()->json(['message' => 'Training record updated.'], SymfonyResponse::HTTP_OK);
    }

    /**
     * DELETE /api/quality/staff-trainings/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $training = StaffTraining::findOrFail($id);
        if ($r = $this->gateWrite($training->laboratory_id)) return $r;

        $training->delete();

        return response()->json(['message' => 'Training record deleted.'], SymfonyResponse::HTTP_OK);
    }

    /**
     * GET /api/quality/staff-trainings/lab-staff/{laboratoryId}
     * Active staff list for a lab — feeds the "Staff member" dropdown
     * on the entry form.
     */
    public function labStaff(int $laboratoryId): JsonResponse
    {
        if ($r = $this->gateRead()) return $r;

        $users = User::query()
            ->whereHas('laboratories', fn($q) => $q->where('laboratories.id', $laboratoryId))
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $users], SymfonyResponse::HTTP_OK);
    }
}
