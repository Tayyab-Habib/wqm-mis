<?php

namespace App\Http\Controllers\Laboratories;

use App\Enums\WaterSampleResultEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ShowLaboratoryWaterQualityAnalysisReportRequest;
use App\Models\Laboratories\Laboratory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class LaboratoryWaterQualityAnalysisReportController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(ShowLaboratoryWaterQualityAnalysisReportRequest $request): JsonResponse
    {
        $laboratory = Laboratory::query()
            ->select('id', 'name', 'district_id')
            ->with([
                'district:id,name',
            ])
            ->whereHas('waterSamples', function ($query) use ($request) {
                $query->whereBetween('sampled_at', [
                    Carbon::parse($request->month)->startOfMonth(),
                    Carbon::parse($request->month)->endOfMonth()
                ]);
            })
            ->withCount([
                'waterSamples as total_water_samples',
                'waterSamples as total_fit_water_samples' => function ($query) {
                    $query->where('result', '=', WaterSampleResultEnum::FIT->value);
                },
                'waterSamples as total_not_tested' => function ($query) {
                    $query->whereNull('result');
                },
                'waterSamples as total_unfit_water_samples' => function ($query) {
                    $query->where('result', '=', WaterSampleResultEnum::UNFIT->value);
                },
            ])
            ->get();


        return response()->json([
            'message' => 'Success fetching water quality analysis report',
            'data' => $laboratory,
        ], SymfonyResponse::HTTP_OK);
    }
}
