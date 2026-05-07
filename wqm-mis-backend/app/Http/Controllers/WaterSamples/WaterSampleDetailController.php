<?php

namespace App\Http\Controllers\WaterSamples;

use App\Http\Controllers\Controller;
use App\Http\Requests\WaterSampleDetail\DeleteWaterSampleDetailRequest;
use App\Http\Requests\WaterSampleDetail\ShowWaterSampleDetailRequest;
use App\Http\Requests\WaterSampleDetail\StoreWaterSampleDetailRequest;
use App\Http\Requests\WaterSampleDetail\UpdateWaterSampleDetailRequest;
use App\Models\WaterSamples\WaterSampleDetail;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class WaterSampleDetailController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param StoreWaterSampleDetailRequest $request
     * @return JsonResponse
     */
    public function store(StoreWaterSampleDetailRequest $request)
    {
        $validatedData = $request->validated();

        $waterSampleDetail = WaterSampleDetail::query()
            ->create([
                'water_sample_id' => $validatedData['water_sample_id'],
                'test_id' => $validatedData['test_id'],
            ]);
        return response()->json([
            'message' => 'Success creating water sample detail',
            'data' => $waterSampleDetail
        ], SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowWaterSampleDetailRequest $request
     * @param WaterSampleDetail $waterSampleDetail
     * @return JsonResponse
     */
    public function show(ShowWaterSampleDetailRequest $request, WaterSampleDetail $waterSampleDetail)
    {
        $waterSampleDetail->load(['waterSample', 'test']);

        return response()->json([
            'message' => 'Success fetching water sample detail',
            'data' => $waterSampleDetail,
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateWaterSampleDetailRequest $request
     * @param WaterSampleDetail $waterSampleDetail
     * @return JsonResponse
     */
    public function update(UpdateWaterSampleDetailRequest $request, WaterSampleDetail $waterSampleDetail)
    {
        $waterSampleDetail->update($request->validated());

        if ($waterSampleDetail->wasChanged()) {
            return response()->json([
                'message' => 'Success updating water sample detail',
                'data' => $waterSampleDetail
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Error updating water sample detail'
        ], SymfonyResponse::HTTP_BAD_REQUEST);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DeleteWaterSampleDetailRequest $request
     * @param WaterSampleDetail $waterSampleDetail
     * @return JsonResponse
     */
    public function destroy(DeleteWaterSampleDetailRequest $request, WaterSampleDetail $waterSampleDetail)
    {
        $waterSampleDetail->delete();

        return response()->json([
            'message' => 'Success deleting water sample detail',
            'data' => null
        ], SymfonyResponse::HTTP_OK);
    }
}
