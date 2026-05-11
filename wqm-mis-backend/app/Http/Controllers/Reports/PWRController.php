<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PWRController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'from_date'       => ['nullable', 'date'],
            'to_date'         => ['nullable', 'date', 'after_or_equal:from_date'],
            'region_id'       => ['nullable', 'exists:regions,id'],
            'division_id'     => ['nullable', 'exists:divisions,id'],
            'circle_id'       => ['nullable', 'exists:circles,id'],
            'district_id'     => ['nullable', 'exists:districts,id'],
            'phed_division_id'=> ['nullable', 'exists:phed_divisions,id'],
            'laboratory_id'   => ['nullable', 'exists:laboratories,id'],
            'test_type'       => ['nullable', 'string'],   // PHE / Private / PT
            'test_id'         => ['nullable', 'exists:tests,id'],
        ]);

        // ── Base sample filter ────────────────────────────────────────
        $sampleQuery = DB::table('water_samples')
            ->whereNull('water_samples.deleted_at')
            ->where('water_samples.is_draft', 0)
            ->when($request->filled('from_date'),        fn($q) => $q->whereDate('water_samples.sampled_at', '>=', $request->from_date))
            ->when($request->filled('to_date'),          fn($q) => $q->whereDate('water_samples.sampled_at', '<=', $request->to_date))
            ->when($request->filled('region_id'),        fn($q) => $q->where('water_samples.region_id',        $request->region_id))
            ->when($request->filled('division_id'),      fn($q) => $q->where('water_samples.division_id',      $request->division_id))
            ->when($request->filled('circle_id'),        fn($q) => $q->where('water_samples.circle_id',        $request->circle_id))
            ->when($request->filled('district_id'),      fn($q) => $q->where('water_samples.district_id',      $request->district_id))
            ->when($request->filled('phed_division_id'), fn($q) => $q->where('water_samples.phed_division_id', $request->phed_division_id))
            ->when($request->filled('laboratory_id'),    fn($q) => $q->where('water_samples.laboratory_id',    $request->laboratory_id))
            ->when($request->filled('test_type'),        fn($q) => $q->where('water_samples.test_type',        $request->test_type));

        $sampleIds = (clone $sampleQuery)->pluck('water_samples.id');

        Log::info('PWR sample count', ['count' => $sampleIds->count(), 'filters' => $request->all()]);

        // ── Fetch all tests (parameters) ──────────────────────────────
        $testsQuery = DB::table('tests')
            ->whereNull('deleted_at')
            ->select('id', 'water_quality_parameter', 'type', 'unit', 'permissible_limits', 'criteria', 'who_guideline_end');

        if ($request->filled('test_id')) {
            $testsQuery->where('id', $request->test_id);
        }

        $allTests = $testsQuery->get()->keyBy('id');

        // ── Parameter-wise aggregation ────────────────────────────────
        // For each test, count how many sample_details have a numeric result
        // and how many exceed the WHO guideline end (for criteria=1 tests)
        $detailAgg = DB::table('water_sample_details')
            ->whereIn('water_sample_id', $sampleIds)
            ->whereNull('water_sample_details.deleted_at')
            ->whereNotNull('analysis_result')
            ->where('analysis_result', '!=', '')
            ->when($request->filled('test_id'), fn($q) => $q->where('test_id', $request->test_id))
            ->select(
                'test_id',
                DB::raw('COUNT(*) as total_tested'),
                DB::raw('SUM(CASE WHEN analysis_result REGEXP \'^-?[0-9]+\\.?[0-9]*$\' THEN 1 ELSE 0 END) as numeric_count')
            )
            ->groupBy('test_id')
            ->get()
            ->keyBy('test_id');

        // For criteria=1 tests, count exceeding separately using the who_guideline_end
        $exceedingByTest = [];
        foreach ($allTests as $testId => $test) {
            if (!$test->criteria || $test->who_guideline_end == 0) {
                $exceedingByTest[$testId] = 0;
                continue;
            }
            $exceedingByTest[$testId] = DB::table('water_sample_details')
                ->whereIn('water_sample_id', $sampleIds)
                ->whereNull('water_sample_details.deleted_at')
                ->where('test_id', $testId)
                ->whereNotNull('analysis_result')
                ->where('analysis_result', '!=', '')
                ->whereRaw('analysis_result REGEXP \'^-?[0-9]+\\.?[0-9]*$\'')
                ->whereRaw('CAST(analysis_result AS DECIMAL(15,4)) > ?', [$test->who_guideline_end])
                ->count();
        }

        // ── Build parameter overview ──────────────────────────────────
        $paramOverview = [];
        $totalTested   = 0;
        $totalExceeding = 0;

        foreach ($allTests as $testId => $test) {
            $agg     = $detailAgg->get($testId);
            $tested  = $agg ? (int)$agg->total_tested : 0;
            $exceeding = $exceedingByTest[$testId] ?? 0;

            if ($tested === 0) continue; // skip params with no data

            $pct   = $tested > 0 ? round(($exceeding / $tested) * 100, 1) : 0;
            $ratio = $tested > 0 ? $exceeding / $tested : 0;

            $riskLevel = 'Grey';
            if ($test->criteria) {
                if ($ratio > 0.2)      $riskLevel = 'Red';
                elseif ($ratio > 0.1)  $riskLevel = 'Amber';
                elseif ($ratio > 0)    $riskLevel = 'Green';
                else                   $riskLevel = 'Green';
            }

            $paramOverview[] = [
                'test_id'    => $testId,
                'parameter'  => $test->water_quality_parameter,
                'type'       => $test->type,
                'unit'       => $test->unit,
                'limit'      => $test->permissible_limits,
                'criteria'   => (bool)$test->criteria,
                'tested'     => $tested,
                'exceeding'  => $exceeding,
                'pct'        => $pct,
                'risk_level' => $riskLevel,
            ];

            $totalTested    += $tested;
            $totalExceeding += $exceeding;
        }

        // Sort by % exceeding descending
        usort($paramOverview, fn($a, $b) => $b['pct'] <=> $a['pct']);

        // ── District-wise breakdown ───────────────────────────────────
        // For the selected parameter (or all), count per district
        $districtAgg = DB::table('water_sample_details as wsd')
            ->join('water_samples as ws', 'ws.id', '=', 'wsd.water_sample_id')
            ->whereIn('wsd.water_sample_id', $sampleIds)
            ->whereNull('wsd.deleted_at')
            ->whereNotNull('wsd.analysis_result')
            ->where('wsd.analysis_result', '!=', '')
            ->when($request->filled('test_id'), fn($q) => $q->where('wsd.test_id', $request->test_id))
            ->selectRaw('
                ws.district_id,
                COUNT(*) as total,
                SUM(CASE WHEN ws.result = "Fit" OR ws.result = "1" THEN 1 ELSE 0 END) as fit,
                SUM(CASE WHEN ws.result = "Unfit" OR ws.result = "2" THEN 1 ELSE 0 END) as unfit
            ')
            ->groupBy('ws.district_id')
            ->get();

        // If a specific parameter is selected, use parameter-level exceeding per district
        if ($request->filled('test_id')) {
            $test = $allTests->get($request->test_id);
            $districtAgg = DB::table('water_sample_details as wsd')
                ->join('water_samples as ws', 'ws.id', '=', 'wsd.water_sample_id')
                ->whereIn('wsd.water_sample_id', $sampleIds)
                ->whereNull('wsd.deleted_at')
                ->where('wsd.test_id', $request->test_id)
                ->whereNotNull('wsd.analysis_result')
                ->where('wsd.analysis_result', '!=', '')
                ->whereRaw('wsd.analysis_result REGEXP \'^-?[0-9]+\\.?[0-9]*$\'')
                ->selectRaw('
                    ws.district_id,
                    COUNT(*) as total,
                    SUM(CASE WHEN CAST(wsd.analysis_result AS DECIMAL(15,4)) <= ? THEN 1 ELSE 0 END) as fit,
                    SUM(CASE WHEN CAST(wsd.analysis_result AS DECIMAL(15,4)) > ? THEN 1 ELSE 0 END) as unfit
                ', [$test?->who_guideline_end ?? 0, $test?->who_guideline_end ?? 0])
                ->groupBy('ws.district_id')
                ->get();
        }

        $districtIds = $districtAgg->pluck('district_id')->filter()->unique()->values();
        $districtNames = DB::table('districts')
            ->whereNull('deleted_at')
            ->whereIn('id', $districtIds)
            ->pluck('name', 'id');

        $districtBreakdown = $districtAgg->map(function ($row) use ($districtNames) {
            $total     = (int)$row->total;
            $fit       = (int)$row->fit;
            $unfit     = (int)$row->unfit;
            $pct       = $total > 0 ? round(($unfit / $total) * 100, 1) : 0;
            $ratio     = $total > 0 ? $unfit / $total : 0;

            return [
                'district_id'   => $row->district_id,
                'district_name' => $districtNames[$row->district_id] ?? 'Unknown',
                'total'         => $total,
                'fit'           => $fit,
                'unfit'         => $unfit,
                'pct'           => $pct,
                'remarks'       => $ratio > 0.2 ? 'Action Required' : ($ratio > 0.1 ? 'Monitor' : 'No Action'),
            ];
        })->sortByDesc('pct')->values()->toArray();

        // ── KP Totals ─────────────────────────────────────────────────
        $kpTotals = [
            'total_tested'   => $totalTested,
            'total_exceeding'=> $totalExceeding,
            'pct'            => $totalTested > 0 ? round(($totalExceeding / $totalTested) * 100, 1) : 0,
        ];

        return response()->json([
            'param_overview'     => $paramOverview,
            'district_breakdown' => $districtBreakdown,
            'kp_totals'          => $kpTotals,
        ], SymfonyResponse::HTTP_OK);
    }
}
