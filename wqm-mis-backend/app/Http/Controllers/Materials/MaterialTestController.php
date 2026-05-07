<?php

namespace App\Http\Controllers\Materials;

use App\Http\Controllers\Controller;
use App\Http\Requests\Material\StoreMaterialTestRequest;
use App\Http\Requests\Material\UpdateMaterialTestRequest;
use App\Models\MaterialTest;
use Illuminate\Http\JsonResponse;

class MaterialTestController extends Controller
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
     * @param  StoreMaterialTestRequest  $request
     * @return JsonResponse
     */
    public function store(StoreMaterialTestRequest $request): JsonResponse
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param MaterialTest $materialTest
     * @return JsonResponse
     */
    public function show(MaterialTest $materialTest): JsonResponse
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  UpdateMaterialTestRequest  $request
     * @param MaterialTest $materialTest
     * @return JsonResponse
     */
    public function update(UpdateMaterialTestRequest $request, MaterialTest $materialTest): JsonResponse
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param MaterialTest $materialTest
     * @return JsonResponse
     */
    public function destroy(MaterialTest $materialTest): JsonResponse
    {
        //
    }
}
