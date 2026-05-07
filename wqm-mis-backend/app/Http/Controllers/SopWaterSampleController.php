<?php

namespace App\Http\Controllers;

use App\Enums\SopWaterSampleEnum;
use App\Http\Requests\SopWaterSample\StoreSopWaterSampleRequest;
use App\Http\Requests\SopWaterSample\ShowSopWaterSampleRequest;
use App\Models\SopWaterSample;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SopWaterSampleController extends Controller
{

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreSopWaterSampleRequest $request
     * @return JsonResponse
     */
    public function store(StoreSopWaterSampleRequest $request): JsonResponse
    {
        $sopWaterSample = SopWaterSample::query()
            ->create(array_merge($request->validated(), ['user_id' => auth()->id()]));

        return response()->json([
            'message' => 'Success creating SOP\'s',
            'data' => $sopWaterSample
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowSopWaterSampleRequest $request
     * @param SopWaterSampleEnum $sopWaterSampleEnum
     * @return JsonResponse
     */
    public function show(ShowSopWaterSampleRequest $request, SopWaterSampleEnum $sopWaterSampleEnum): JsonResponse
    {
        $sopWaterSample = SopWaterSample::query()
            ->where('type', $sopWaterSampleEnum->value)
            ->latest()
            ->first();

        return response()->json([
            'message' => 'Success fetching SOP\'s',
            'data' => $sopWaterSample
        ], SymfonyResponse::HTTP_OK);
    }

}
