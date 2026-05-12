<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'sample_type'     => ['nullable', 'in:PHE,Private,PT'],   // collectable_type
            'test_type'       => ['nullable', 'string'],              // accepted for back-compat
            'test_id'         => ['nullable', 'exists:tests,id'],
        ]);

        // sample_type → collectable_type (PHE = User class; Private = anything else; PT future)
        $sampleType = $request->input('sample_type') ?? $request->input('test_type');

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
            // sample_type maps to collectable_type (User class = PHE)
            ->when($sampleType === 'PHE',     fn($q) => $q->where('water_samples.collectable_type', User::class))
            ->when($sampleType === 'Private', fn($q) => $q->where('water_samples.collectable_type', '!=', User::class));

        $sampleIds = (clone $sampleQuery)->pluck('water_samples.id');

        // ── Fetch all ACTIVE tests (parameters) per SRS §2.2 R-07 ─────
        // SRS: "covers every active parameter regardless of whether samples exceed limits"
        $testsQuery = DB::table('tests')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->select('id', 'water_quality_parameter', 'type', 'unit', 'permissible_limits', 'criteria',
                     'who_guideline_start', 'who_guideline_end',
                     'laboratory_guideline_start', 'laboratory_guideline_end',
                     'display_order')
            ->orderBy('display_order')
            ->orderBy('water_quality_parameter');

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

        // For criteria=1 tests, count exceeding using both bounds of the WHO range
        // (e.g. pH 6.5-8.5 → value < 6.5 OR value > 8.5). Falls back to laboratory guidelines if WHO is missing.
        $exceedingByTest = [];
        foreach ($allTests as $testId => $test) {
            if (!$test->criteria) { $exceedingByTest[$testId] = 0; continue; }

            $minRaw = $test->who_guideline_start ?? $test->laboratory_guideline_start;
            $maxRaw = $test->who_guideline_end   ?? $test->laboratory_guideline_end;
            $hasMin = $minRaw !== null && $minRaw !== '' && is_numeric($minRaw);
            $hasMax = $maxRaw !== null && $maxRaw !== '' && is_numeric($maxRaw);
            if (!$hasMin && !$hasMax) { $exceedingByTest[$testId] = 0; continue; }

            $q = DB::table('water_sample_details')
                ->whereIn('water_sample_id', $sampleIds)
                ->whereNull('water_sample_details.deleted_at')
                ->where('test_id', $testId)
                ->whereNotNull('analysis_result')
                ->where('analysis_result', '!=', '')
                ->whereRaw('analysis_result REGEXP \'^-?[0-9]+\\.?[0-9]*$\'')
                ->where(function ($w) use ($hasMin, $hasMax, $minRaw, $maxRaw) {
                    if ($hasMin) $w->orWhereRaw('CAST(analysis_result AS DECIMAL(15,4)) < ?', [(float)$minRaw]);
                    if ($hasMax) $w->orWhereRaw('CAST(analysis_result AS DECIMAL(15,4)) > ?', [(float)$maxRaw]);
                });

            $exceedingByTest[$testId] = $q->count();
        }

        // ── Build parameter overview ──────────────────────────────────
        // Per SRS: include every active parameter, even with 0 tests.
        $paramOverview  = [];
        $totalTested    = 0;
        $totalExceeding = 0;

        foreach ($allTests as $testId => $test) {
            $agg       = $detailAgg->get($testId);
            $tested    = $agg ? (int)$agg->total_tested : 0;
            $exceeding = $exceedingByTest[$testId] ?? 0;

            $pct   = $tested > 0 ? round(($exceeding / $tested) * 100, 1) : 0;
            $ratio = $tested > 0 ? $exceeding / $tested : 0;

            $riskLevel = 'Grey';
            if ($test->criteria && $tested > 0) {
                if ($ratio > 0.2)      $riskLevel = 'Red';
                elseif ($ratio > 0.1)  $riskLevel = 'Amber';
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

        // If a specific parameter is selected, compute per-district exceeding using BOTH bounds
        if ($request->filled('test_id')) {
            $test  = $allTests->get($request->test_id);
            $minRaw = $test->who_guideline_start ?? $test->laboratory_guideline_start ?? null;
            $maxRaw = $test->who_guideline_end   ?? $test->laboratory_guideline_end   ?? null;
            $minVal = is_numeric($minRaw) ? (float)$minRaw : null;
            $maxVal = is_numeric($maxRaw) ? (float)$maxRaw : null;

            // Build the "fit" / "unfit" SQL fragments dynamically per which bounds exist
            $unfitConds = [];
            $params     = [];
            if ($minVal !== null) { $unfitConds[] = 'CAST(wsd.analysis_result AS DECIMAL(15,4)) < ?'; $params[] = $minVal; }
            if ($maxVal !== null) { $unfitConds[] = 'CAST(wsd.analysis_result AS DECIMAL(15,4)) > ?'; $params[] = $maxVal; }
            $unfitExpr = empty($unfitConds) ? '0' : '(' . implode(' OR ', $unfitConds) . ')';

            $districtAgg = DB::table('water_sample_details as wsd')
                ->join('water_samples as ws', 'ws.id', '=', 'wsd.water_sample_id')
                ->whereIn('wsd.water_sample_id', $sampleIds)
                ->whereNull('wsd.deleted_at')
                ->where('wsd.test_id', $request->test_id)
                ->whereNotNull('wsd.analysis_result')
                ->where('wsd.analysis_result', '!=', '')
                ->whereRaw('wsd.analysis_result REGEXP \'^-?[0-9]+\\.?[0-9]*$\'')
                ->selectRaw(
                    "ws.district_id,
                     COUNT(*) as total,
                     SUM(CASE WHEN {$unfitExpr} THEN 0 ELSE 1 END) as fit,
                     SUM(CASE WHEN {$unfitExpr} THEN 1 ELSE 0 END) as unfit",
                    array_merge($params, $params)
                )
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
