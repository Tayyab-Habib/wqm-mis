<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KpiLabPeriod;
use App\Models\Laboratories\Laboratory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Admin → KPI Framework page.
 *
 * Reads come from the shared DashboardController::labKpis() matrix (admin
 * has unscoped access, so it already returns all labs with the 5 computed
 * KPIs + the 4 manual ones merged from kpi_lab_periods).
 *
 * This controller owns the WRITE side: the monthly per-lab snapshot for
 * KPI-001 / 007 / 008 / 009.
 */
class KpiFrameworkController extends Controller
{
    /** KPIs the admin enters by hand. Everything else is computed live. */
    private const MANUAL_KPI_CODES = ['KPI-001', 'KPI-007', 'KPI-008', 'KPI-009'];

    /** Single auth + RBAC gate for every action on this controller. */
    private function gate(): ?JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], SymfonyResponse::HTTP_UNAUTHORIZED);
        }
        if (!$user->isUnscoped() && !$user->can('manage_kpi_framework')) {
            return response()->json(['message' => 'Forbidden.'], SymfonyResponse::HTTP_FORBIDDEN);
        }
        return null;
    }

    /**
     * POST /api/admin/kpi-framework/save
     *
     * Upsert a (lab, kpi_code, period) row. Idempotent — re-submitting the
     * same period overwrites numerator/denominator/notes.
     */
    public function save(Request $request): JsonResponse
    {
        if ($r = $this->gate()) return $r;

        $validated = $request->validate([
            'laboratory_id' => ['required', 'integer', 'exists:laboratories,id'],
            'kpi_code'      => ['required', 'string', 'in:' . implode(',', self::MANUAL_KPI_CODES)],
            'period'        => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'numerator'     => ['required', 'integer', 'min:0'],
            'denominator'   => ['required', 'integer', 'min:0'],
            'notes'         => ['nullable', 'string', 'max:1000'],
        ], [
            'period.regex' => 'Period must be in YYYY-MM format (e.g. 2026-05).',
        ]);

        // Numerator cannot exceed denominator — KPI values cap at 100%.
        if ($validated['numerator'] > $validated['denominator']) {
            return response()->json([
                'message' => 'Numerator cannot be greater than denominator.',
                'errors'  => ['numerator' => ['Numerator cannot exceed denominator.']],
            ], SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $row = KpiLabPeriod::updateOrCreate(
            [
                'laboratory_id' => $validated['laboratory_id'],
                'kpi_code'      => $validated['kpi_code'],
                'period'        => $validated['period'],
            ],
            [
                'numerator'   => $validated['numerator'],
                'denominator' => $validated['denominator'],
                'notes'       => $validated['notes'] ?? null,
                'created_by'  => auth()->id(),
            ]
        );

        return response()->json([
            'message' => 'KPI value saved.',
            'data'    => [
                'id'            => $row->id,
                'laboratory_id' => $row->laboratory_id,
                'kpi_code'      => $row->kpi_code,
                'period'        => $row->period,
                'numerator'     => $row->numerator,
                'denominator'   => $row->denominator,
                'value'         => $row->value,           // computed accessor
                'notes'         => $row->notes,
            ],
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * GET /api/admin/kpi-framework/history?laboratory_id=&kpi_code=
     *
     * Prior monthly entries for one lab+kpi — newest first. Lets the admin
     * see what was previously submitted and adjust if needed.
     */
    public function history(Request $request): JsonResponse
    {
        if ($r = $this->gate()) return $r;

        $validated = $request->validate([
            'laboratory_id' => ['required', 'integer', 'exists:laboratories,id'],
            'kpi_code'      => ['required', 'string', 'in:' . implode(',', self::MANUAL_KPI_CODES)],
            'limit'         => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $rows = KpiLabPeriod::query()
            ->with('creator:id,name')
            ->where('laboratory_id', $validated['laboratory_id'])
            ->where('kpi_code', $validated['kpi_code'])
            ->orderByDesc('period')
            ->limit($validated['limit'] ?? 24)
            ->get()
            ->map(fn(KpiLabPeriod $r) => [
                'id'          => $r->id,
                'period'      => $r->period,
                'numerator'   => $r->numerator,
                'denominator' => $r->denominator,
                'value'       => $r->value,
                'notes'       => $r->notes,
                'created_by'  => $r->creator?->name,
                'updated_at'  => $r->updated_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $rows], SymfonyResponse::HTTP_OK);
    }

    /**
     * GET /api/admin/kpi-framework/labs
     *
     * Lab list for the modal's lab dropdown. Same shape as the labs key
     * returned by /dashboard/lab-kpis, but unscoped (always all labs).
     */
    public function labs(): JsonResponse
    {
        if ($r = $this->gate()) return $r;

        $labs = Laboratory::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $labs], SymfonyResponse::HTTP_OK);
    }
}
