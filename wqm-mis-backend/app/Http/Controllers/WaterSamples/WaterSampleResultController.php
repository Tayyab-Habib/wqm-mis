<?php

namespace App\Http\Controllers\WaterSamples;

use App\Enums\WaterSampleResultEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\WaterSampleDetail\UpdateWaterSampleResultRequest;
use App\Models\Scopes\LatestScope;
use App\Models\Test;
use App\Models\WaterSamples\WaterSample;
use App\Models\WaterSamples\WaterSampleDetail;
use App\Services\CalculateWaterQualityParameterService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class WaterSampleResultController extends Controller
{
    /**
     * Update the specified resource in storage.
     *
     * @param UpdateWaterSampleResultRequest $request
     * @param WaterSample $waterSample
     * @return JsonResponse
     */
    public function update(UpdateWaterSampleResultRequest $request, WaterSample $waterSample)
    {
        $validatedData = $request->validated();
        $authUserId = auth()->id();
        $testResults = collect($validatedData['analysis_results']);

        $testIds = $testResults->pluck('test_id');
        $waterParameters = Test::query()
            ->select(['id', 'unit', 'criteria', 'water_quality_parameter', 'who_guideline_start', 'who_guideline_end', 'laboratory_guideline_start', 'laboratory_guideline_end'])
            ->whereIn('id', $testIds)
            ->withoutGlobalScope(LatestScope::class)
            ->get()
            ->transform(function ($waterParameter) use ($testResults, $waterSample) {
                $testResult = $testResults->where('test_id', '=', $waterParameter->id)->first();
                return collect(array_merge($waterParameter->toArray(), ['input_result' => $testResult['analysis_result'], 'analysis_result' => $testResult['analysis_result'], 'water_sample_id' => $waterSample->id]));
//                return $waterParameter->merge(['analysis_result' => $testResult['analysis_result']]);
            });

        $desiredTests = $waterSample->desired_test;

        $waterQualityParameterResults = (new CalculateWaterQualityParameterService($waterParameters, $desiredTests))
            ->calculateAnalysisResult();

        $waterParameterResult = [];

        $notTested = 0;
        foreach ($waterQualityParameterResults as $waterParameter) {
            if ($waterParameter['analysis_result'] === 'NT') {
                $notTested++;
            }
            if ($waterParameter['criteria']) {
                $waterParameterResult[] = ((float)$waterParameter['analysis_result'] > (float)$waterParameter['who_guideline_end']
                    || (float)$waterParameter['analysis_result'] < (float)$waterParameter['who_guideline_start']) && ($waterParameter['analysis_result'] !== 'NT')
                || (float)$waterParameter['analysis_result'] > (float)$waterParameter['laboratory_guideline_end'] || ($waterParameter['id'] === 20 && $waterParameter['analysis_result'] === '+ve')
                    ? 'Unfit'
                    : 'Fit';
            }
        }

        try {
            WaterSampleDetail::query()
                ->upsert($waterQualityParameterResults->map(function ($waterParameter) use ($authUserId) {
                    return [
                        'water_sample_id' => $waterParameter['water_sample_id'],
                        'test_id' => $waterParameter['id'],
                        'input_result' => $waterParameter['input_result'],
                        'analysis_result' => $waterParameter['analysis_result'],
                        'analyst_id' => $authUserId,
                    ];
                })->toArray(), ['water_sample_id', 'test_id'], ['analysis_result', 'input_result', 'analyst_id']);

            $waterParameterResult = $notTested !== $waterQualityParameterResults->count() && !$request->is_draft
                ? (in_array('Unfit', $waterParameterResult)
                    ? 'Unfit'
                    : 'Fit')
                : null;

            $waterSample->update([
                'result' => $waterParameterResult,
                'analyzed_at' => now(),
                'remarks' => $request->remarks,
                'is_draft' => $request->is_draft,
                'research_officer_id' => $authUserId
            ]);

            $message = $request->is_draft ? 'Success creating draft' : 'Success updating water sample analysis';

        } catch (\Exception $exception) {
            info($exception->getMessage());

            return response()->json([
                'message' => 'Error creating sample detail',
                'data' => null,
            ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'message' => $message,
            'data' => $waterSample->load('waterSampleDetails'),
        ], SymfonyResponse::HTTP_OK);
    }
}
