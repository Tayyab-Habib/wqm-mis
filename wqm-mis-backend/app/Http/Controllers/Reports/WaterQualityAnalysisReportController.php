<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ShowWaterQualityAnalysisReportRequest;
use App\Models\WaterSamples\WaterSample;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class WaterQualityAnalysisReportController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @param WaterSample $water_sample
     * @return JsonResponse
     */
    public function __invoke(ShowWaterQualityAnalysisReportRequest $request, WaterSample $water_sample): JsonResponse
    {
        $waterSamples = WaterSample::query()
            ->select([
                'id',  // ← required for eager-loading hasMany relations (waterSampleDetails)
                'water_scheme_id', 'slug', 'sample_name', 'water_sample_address',
                'collected_by', 'collectable_type', 'collectable_id',
                'sampled_at', 'latitude', 'longitude',
                'status', 'desired_test', 'result', 'remarks',
                'district_id', 'division_id', 'phed_division_id',
                'laboratory_id', 'region_id', 'circle_id',
            ])
            ->with([
                'waterScheme:id,name',
                'laboratory:id,name',
                'district:id,name',
                'division:id,name',
                'phedDivision:id,name',
                'region:id,name',
                'circle:id,name',
                // For GSR: derive Cause + Specific Ion/Component from failing parameter limits
                'waterSampleDetails:id,water_sample_id,test_id,analysis_result',
                'waterSampleDetails.test:id,water_quality_parameter,unit,type,who_guideline_start,who_guideline_end,laboratory_guideline_start,laboratory_guideline_end',
            ])
            ->where('is_draft', false)
            ->when(isset($request->month), function ($query) use ($request) {
                $query->whereBetween('sampled_at', [
                    Carbon::parse($request->month)->startOfMonth(),
                    Carbon::parse($request->month)->endOfMonth()
                ]);
            })
            // Support from_date / to_date range (used by GAR/GSR/ASR)
            ->when($request->filled('from_date') || $request->filled('to_date'), function ($query) use ($request) {
                $from = $request->filled('from_date')
                    ? Carbon::parse($request->from_date)->startOfDay()
                    : Carbon::parse('2000-01-01');
                $to = $request->filled('to_date')
                    ? Carbon::parse($request->to_date)->endOfDay()
                    : Carbon::now()->endOfDay();
                $query->whereBetween('sampled_at', [$from, $to]);
            })
            ->when($request->filled('district_id'), function ($query) use ($request) {
                $query->where('district_id', '=', $request->district_id);
            })
            ->when($request->filled('division_id'), function ($query) use ($request) {
                $query->where('division_id', '=', $request->division_id);
            })
            ->when($request->filled('sample_type'), function ($query) use ($request) {
                if ($request->sample_type === 'PHE') {
                    $query->where('collectable_type', \App\Models\User::class);
                } elseif ($request->sample_type === 'Private') {
                    $query->where('collectable_type', '!=', \App\Models\User::class);
                }
            })
            ->when($request->filled('water_scheme_id'), function ($query) use ($request) {
                $query->where('water_scheme_id', '=', $request->water_scheme_id);
            })
            ->when($request->filled('laboratory_id'), function ($query) use ($request) {
                $query->where('laboratory_id', '=', $request->laboratory_id);
            })
            ->when($request->filled('result'), function ($query) use ($request) {
                $query->where('result', '=', $request->result);
            })
            ->when($request->filled('region_id'), function ($query) use ($request) {
                $query->where('region_id', '=', $request->region_id);
            })
            ->when($request->filled('circle_id'), function ($query) use ($request) {
                $query->where('circle_id', '=', $request->circle_id);
            })
            ->when($request->filled('phed_division_id'), function ($query) use ($request) {
                $query->where('phed_division_id', '=', $request->phed_division_id);
            })
            ->get();

        if ($waterSamples->isEmpty()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null,
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success fetching water-quality-analysis-report',
            'data' => $waterSamples,
        ], SymfonyResponse::HTTP_OK);
    }
}
