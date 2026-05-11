<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Scopes\LatestScope;
use App\Models\WaterSamples\WaterSample;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CEWiseReportController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'from_date'   => ['nullable', 'date'],
            'to_date'     => ['nullable', 'date', 'after_or_equal:from_date'],
            'region_id'   => ['nullable', 'exists:regions,id'],
            'division_id' => ['nullable', 'exists:divisions,id'],
            'district_id' => ['nullable', 'exists:districts,id'],
        ]);

        // Use DB facade directly — avoids LatestScope ORDER BY conflict with GROUP BY
        $query = DB::table('water_samples')
            ->whereNull('deleted_at')
            ->where('is_draft', 0)
            ->when($request->filled('from_date'), fn($q) =>
                $q->whereDate('sampled_at', '>=', $request->from_date))
            ->when($request->filled('to_date'), fn($q) =>
                $q->whereDate('sampled_at', '<=', $request->to_date))
            ->when($request->filled('region_id'), fn($q) =>
                $q->where('region_id', $request->region_id))
            ->when($request->filled('division_id'), fn($q) =>
                $q->where('division_id', $request->division_id))
            ->when($request->filled('district_id'), fn($q) =>
                $q->where('district_id', $request->district_id));

        // Debug log
        Log::info('CE-wise total rows', ['count' => (clone $query)->count(), 'filters' => $request->all()]);

        // ── CE-wise aggregation ───────────────────────────────────────
        $ceRaw = (clone $query)
            ->selectRaw('
                region_id,
                COUNT(*) as total,
                SUM(CASE WHEN result = "Fit"   OR result = "1" THEN 1 ELSE 0 END) as fit,
                SUM(CASE WHEN result = "Unfit" OR result = "2" THEN 1 ELSE 0 END) as unfit,
                COUNT(DISTINCT district_id) as districts_count,
                COUNT(DISTINCT division_id) as divisions_count
            ')
            ->groupBy('region_id')
            ->get();

        // ── District-wise aggregation ─────────────────────────────────
        $districtRaw = (clone $query)
            ->selectRaw('
                district_id,
                division_id,
                region_id,
                COUNT(*) as total,
                SUM(CASE WHEN result = "Fit"   OR result = "1" THEN 1 ELSE 0 END) as fit,
                SUM(CASE WHEN result = "Unfit" OR result = "2" THEN 1 ELSE 0 END) as unfit
            ')
            ->groupBy('district_id', 'division_id', 'region_id')
            ->get();

        Log::info('CE-wise results', ['ce_rows' => $ceRaw->count(), 'district_rows' => $districtRaw->count()]);

        // ── Fetch names separately ────────────────────────────────────
        $regionIds   = $ceRaw->pluck('region_id')->filter()->unique()->values();
        $districtIds = $districtRaw->pluck('district_id')->filter()->unique()->values();
        $divisionIds = $districtRaw->pluck('division_id')->filter()->unique()->values();

        $regionNames   = DB::table('regions')->whereIn('id', $regionIds)->pluck('name', 'id');
        $districtNames = DB::table('districts')->whereNull('deleted_at')->whereIn('id', $districtIds)->pluck('name', 'id');
        $divisionNames = DB::table('divisions')->whereNull('deleted_at')->whereIn('id', $divisionIds)->pluck('name', 'id');

        // ── Map CE summary ────────────────────────────────────────────
        $ceSummary = $ceRaw->map(fn($row) => [
            'region_id'       => $row->region_id,
            'region_name'     => $regionNames[$row->region_id] ?? 'Unknown CE',
            'total'           => (int) $row->total,
            'fit'             => (int) $row->fit,
            'unfit'           => (int) $row->unfit,
            'districts_count' => (int) $row->districts_count,
            'divisions_count' => (int) $row->divisions_count,
        ])->sortBy('region_name')->values();

        // ── Map district detail ───────────────────────────────────────
        $districtDetail = $districtRaw->map(fn($row) => [
            'district_id'   => $row->district_id,
            'district_name' => $districtNames[$row->district_id] ?? 'Unknown District',
            'division_id'   => $row->division_id,
            'division_name' => $divisionNames[$row->division_id] ?? 'Unknown Division',
            'region_id'     => $row->region_id,
            'region_name'   => $regionNames[$row->region_id] ?? 'Unknown CE',
            'total'         => (int) $row->total,
            'fit'           => (int) $row->fit,
            'unfit'         => (int) $row->unfit,
        ])->sortBy('region_name')->values();

        // ── Province totals ───────────────────────────────────────────
        $provinceTotals = [
            'total'           => $ceSummary->sum('total'),
            'fit'             => $ceSummary->sum('fit'),
            'unfit'           => $ceSummary->sum('unfit'),
            'districts_count' => $districtDetail->pluck('district_id')->unique()->count(),
            'divisions_count' => $districtDetail->pluck('division_id')->unique()->count(),
        ];

        return response()->json([
            'message'         => 'CE-wise report generated successfully',
            'ce_summary'      => $ceSummary,
            'district_detail' => $districtDetail,
            'province_totals' => $provinceTotals,
        ], SymfonyResponse::HTTP_OK);
    }
}
