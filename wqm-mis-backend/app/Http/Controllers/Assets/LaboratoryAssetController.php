<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Asset\IndexLaboratoryAssetRequest;
use App\Http\Requests\Asset\ShowLaboratoryAssetRequest;
use App\Http\Requests\Asset\UpdateLaboratoryAssetRequest;
use App\Models\Asset\LaboratoryAsset;
use App\Services\FetchAssetService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class LaboratoryAssetController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(IndexLaboratoryAssetRequest $request)
    {
        $materials = (new FetchAssetService())->fetch();

        return response()->json([
            'message' => 'Success fetching laboratory assets',
            'data' => $materials,
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Asset\LaboratoryAsset  $laboratoryAsset
     * @return JsonResponse
     */
    public function show(ShowLaboratoryAssetRequest $request, LaboratoryAsset $laboratoryAsset)
    {
        return  (new FetchAssetService())->show($laboratoryAsset);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateLaboratoryAssetRequest $request
     * @param LaboratoryAsset $laboratoryAsset
     * @return JsonResponse
     */
    public function update(UpdateLaboratoryAssetRequest $request, LaboratoryAsset $laboratoryAsset)
    {
        $laboratoryAsset->update($request->validated());

        return response()->json([
            'message' => 'Success updating laboratory inventory',
            'data' => $laboratoryAsset,
        ], SymfonyResponse::HTTP_OK);
    }

    public function laboratoryAssets()
    {
        $materials = (new FetchAssetService())->fetchAll();

        if ($materials->isEmpty()) {
            return response()->json([
                'message' => 'No data found',
                'data' => null,
            ], SymfonyResponse::HTTP_NO_CONTENT);
        }

        return response()->json([
            'message' => 'Success fetching laboratories stock',
            'data' => $materials,
        ], SymfonyResponse::HTTP_OK);

    }
}
