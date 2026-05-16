<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ShowWaterQualityAnalysisReportRequest;
use App\Models\User;
use App\Models\WaterSamples\WaterSample;
use App\Services\AuthScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class WaterQualityAnalysisReportController extends Controller
{
    public function __invoke(ShowWaterQualityAnalysisReportRequest $request): JsonResponse
    {
        $query = WaterSample::query();
        // RBAC: scope by user's role. SA/manager/view-only see all; CE/SE/XEN
        // filter by region/circle/phed_division; lab roles see only their lab(s).
        $query = AuthScope::waterSamples($query, $request->user());

        $waterSamples = $query
            ->select([
                'id', 'slug', 'sample_name', 'water_sample_address',
                'collected_by', 'collectable_type', 'collectable_id',
                'sampled_at', 'latitude', 'longitude',
                'status', 'desired_test', 'result', 'test_type',
                'sampling_point', 'source_type', 'remarks',
                'district_id', 'division_id', 'region_id', 'circle_id',
                'phed_division_id', 'laboratory_id', 'water_scheme_id',
                'is_draft',
            ])
            ->with([
                'waterScheme:id,name',
                'laboratory:id,name',
                'district:id,name',
                'division:id,name',
                'region:id,name',
                'circle:id,name',
                'phedDivision:id,name',
                // For GSR: derive Cause + Specific Ion/Component from failing parameter limits
                'waterSampleDetails:id,water_sample_id,test_id,analysis_result',
                'waterSampleDetails.test:id,water_quality_parameter,unit,type,who_guideline_start,who_guideline_end,laboratory_guideline_start,laboratory_guideline_end',
            ])
            ->where('is_draft', false)
            // Date range filter
            ->when($request->filled('from_date'), function ($q) use ($request) {
                $q->whereDate('sampled_at', '>=', $request->from_date);
            })
            ->when($request->filled('to_date'), function ($q) use ($request) {
                $q->whereDate('sampled_at', '<=', $request->to_date);
            })
            // Legacy month filter
            ->when($request->filled('month'), function ($q) use ($request) {
                $q->whereBetween('sampled_at', [
                    Carbon::parse($request->month)->startOfMonth(),
                    Carbon::parse($request->month)->endOfMonth(),
                ]);
            })
            ->when($request->filled('region_id'), function ($q) use ($request) {
                $q->where('region_id', $request->region_id);
            })
            ->when($request->filled('circle_id'), function ($q) use ($request) {
                $q->where('circle_id', $request->circle_id);
            })
            ->when($request->filled('division_id'), function ($q) use ($request) {
                $q->where('division_id', $request->division_id);
            })
            ->when($request->filled('district_id'), function ($q) use ($request) {
                $q->where('district_id', $request->district_id);
            })
            ->when($request->filled('phed_division_id'), function ($q) use ($request) {
                $q->where('phed_division_id', $request->phed_division_id);
            })
            ->when($request->filled('laboratory_id'), function ($q) use ($request) {
                $q->where('laboratory_id', $request->laboratory_id);
            })
            ->when($request->filled('water_scheme_id'), function ($q) use ($request) {
                $q->where('water_scheme_id', $request->water_scheme_id);
            })
            ->when($request->filled('result'), function ($q) use ($request) {
                $q->where('result', $request->result);
            })
            // sample_type is overloaded across reports:
            //  - GAR sends 'PHE' / 'Private' → filter by collectable_type
            //  - GSR sends 'Physical' / 'Microbiological' / etc. → filter by desired_test LIKE
            ->when($request->filled('sample_type'), function ($q) use ($request) {
                $value = $request->sample_type;
                if ($value === 'PHE') {
                    $q->where('collectable_type', User::class);
                } elseif ($value === 'Private') {
                    $q->where('collectable_type', '!=', User::class);
                } else {
                    $q->where('desired_test', 'like', '%' . $value . '%');
                }
            })
            ->get();

        if ($waterSamples->isEmpty()) {
            return response()->json([
                'message' => 'No data to show',
                'data'    => [],
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success fetching water-quality-analysis-report',
            'data'    => $waterSamples,
        ], SymfonyResponse::HTTP_OK);
    }
}
