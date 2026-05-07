<?php

namespace App\Http\Controllers\Materials;

use App\Http\Controllers\Controller;
use App\Http\Requests\Material\StoreLaboratoryMaterialLogRequest;
use App\Http\Requests\Material\UpdateLaboratoryMaterialLogRequest;
use App\Models\Material\LaboratoryMaterialLog;
use Illuminate\Http\JsonResponse;

class LaboratoryMaterialLogController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  StoreLaboratoryMaterialLogRequest  $request
     * @return JsonResponse
     */
    public function store(StoreLaboratoryMaterialLogRequest $request): JsonResponse
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param LaboratoryMaterialLog $laboratoryMaterialLog
     * @return JsonResponse
     */
    public function show(LaboratoryMaterialLog $laboratoryMaterialLog): JsonResponse
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  UpdateLaboratoryMaterialLogRequest  $request
     * @param LaboratoryMaterialLog $laboratoryMaterialLog
     * @return JsonResponse
     */
    public function update(UpdateLaboratoryMaterialLogRequest $request, LaboratoryMaterialLog $laboratoryMaterialLog): JsonResponse
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param LaboratoryMaterialLog $laboratoryMaterialLog
     * @return JsonResponse
     */
    public function destroy(LaboratoryMaterialLog $laboratoryMaterialLog): JsonResponse
    {
        //
    }
}
