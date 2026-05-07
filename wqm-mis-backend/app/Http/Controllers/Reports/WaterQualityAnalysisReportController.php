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
            ->select(['water_scheme_id', 'slug', 'sample_name','collected_by', 'collectable_type', 'collectable_id', 'sampled_at','latitude','longitude', 'status', 'desired_test', 'result', 'district_id', 'laboratory_id'])
            ->with([
                'waterScheme:id,name',
                'laboratory:id,name',
                'district:id,name'
            ])
            ->when(isset($request->month), function ($query) use ($request) {
                $query->whereBetween('sampled_at', [
                    Carbon::parse($request->month)->startOfMonth(),
                    Carbon::parse($request->month)->endOfMonth()
                ]);
            })
            ->when(isset($request->district_id), function ($query) use ($request) {
                $query->where('district_id', '=', $request->district_id)
                    ->where('division_id', '=', $request->division_id);
            })
            ->when(isset($request->division_id), function ($query) use ($request) {
                $query->where('division_id', '=', $request->division_id);
            })
            ->when(isset($request->water_scheme_id), function ($query) use ($request) {
                $query->where('water_scheme_id', '=', $request->water_scheme_id);
            })
            ->when(isset($request->laboratory_id), function ($query) use ($request) {
                $query->where('laboratory_id', '=', $request->laboratory_id);
            })
            ->when(isset($request->result), function ($query) use ($request) {
                $query->where('result', '=', $request->result);
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
