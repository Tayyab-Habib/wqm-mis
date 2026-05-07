<?php

namespace App\Http\Controllers;

use App\Http\Requests\WaterSampleDetail\UpdateTestReportRequest;
use App\Models\WaterSamples\WaterSample;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;


class TestReportController extends Controller
{
    /**
     * Update the specified resource in storage.
     *
     * @param UpdateTestReportRequest $request
     * @param WaterSample $waterSample
     * @return JsonResponse
     */
    public function update(UpdateTestReportRequest $request, WaterSample $waterSample)
    {
        $validatedData = $request->validated();
        $authUser = auth()->id();

        $waterSample->update([
            'remarks' => $validatedData['remarks'],
            'lab_incharge_id' => $authUser
        ]);

        return response()->json([
            'message' => 'Success updating water sample',
            'data' => $waterSample,
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Display the specified resource.
     *
     * @param WaterSample $waterSample
     * @return JsonResponse
     */
    public function show(WaterSample $waterSample): JsonResponse
    {
        $testReport = $waterSample
            ->load(['waterSampleDetails.test']);

        return response()->json([
            'message' => 'Success fetching test report',
            'data' => $testReport,
        ], SymfonyResponse::HTTP_OK);
    }
}
