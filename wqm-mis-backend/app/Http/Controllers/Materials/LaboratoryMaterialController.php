<?php

namespace App\Http\Controllers\Materials;

use App\Http\Controllers\Controller;
use App\Http\Requests\Material\IndexLaboratoryMaterialRequest;
use App\Http\Requests\Material\ShowLaboratoryMaterialRequest;
use App\Http\Requests\Material\UpdateLaboratoryMaterialLogRequest;
use App\Models\Material\LaboratoryMaterial;
use App\Services\FetchMaterialService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class LaboratoryMaterialController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(IndexLaboratoryMaterialRequest $request): JsonResponse
    {
        $fetchMaterials = new FetchMaterialService();

        $materials = $fetchMaterials->fetch();

        return response()->json([
            'message' => 'Success fetching laboratory materials',
            'data' => $materials,
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Display the specified laboratory detials.
     *
     * @param LaboratoryMaterial $laboratoryMaterial
     * @param ShowLaboratoryMaterialRequest $request
     * @return JsonResponse
     */
    public function show(ShowLaboratoryMaterialRequest $request, LaboratoryMaterial $laboratoryMaterial): JsonResponse
    {
        return  (new FetchMaterialService())->show($laboratoryMaterial);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateLaboratoryMaterialLogRequest $request
     * @param LaboratoryMaterial $laboratoryMaterial
     * @return JsonResponse
     */
    public function update(UpdateLaboratoryMaterialLogRequest $request, LaboratoryMaterial $laboratoryMaterial)
    {
        $laboratoryMaterial->update($request->validated());

        return response()->json([
            'message' => 'Success updating laboratory stock',
            'data' => $laboratoryMaterial,
        ], SymfonyResponse::HTTP_OK);
    }

    public function laboratoryMaterials(): JsonResponse
    {
        $materials = (new FetchMaterialService())->fetchAll();

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
