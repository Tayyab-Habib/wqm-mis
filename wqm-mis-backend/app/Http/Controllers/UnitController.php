<?php

namespace App\Http\Controllers;

use App\Http\Requests\Unit\DeleteUnitRequest;
use App\Http\Requests\Unit\ShowUnitRequest;
use App\Http\Requests\Unit\StoreUnitRequest;
use App\Http\Requests\Unit\UpdateUnitRequest;
use App\Http\Requests\Unit\ViewUnitRequest;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class UnitController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(ViewUnitRequest $request)
    {
        $units = Unit::query()->get();

        if ($units->isEmpty()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null,
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success fetching units',
            'data' => $units
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreUnitRequest $request
     * @return JsonResponse
     */
    public function store(StoreUnitRequest $request)
    {
        $unit = Unit::query()
            ->create($request->validated());

        return response()->json([
            'message' => 'Success creating units',
            'data' => $unit
        ], SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowUnitRequest $request
     * @param Unit $unit
     * @return JsonResponse
     */
    public function show(ShowUnitRequest $request, Unit $unit)
    {
        return response()->json([
            'message' => 'Success fetching unit',
            'data' => $unit
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateUnitRequest $request
     * @param \App\Models\Unit $unit
     * @return JsonResponse
     */
    public function update(UpdateUnitRequest $request, Unit $unit)
    {
        $unit->update($request->validated());

        return response()->json([
            'message' => 'Success updating unit',
            'data' => $unit
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DeleteUnitRequest $request
     * @param Unit $unit
     * @return JsonResponse
     */
    public function destroy(DeleteUnitRequest $request, Unit $unit)
    {
        $isExist = $unit->loadExists(['materials', 'assets']);
        if($isExist->materials_exists || $isExist->assets_exists) {
            return response()->json([
                'message' => 'Error deleting unit, delete all records belonging to this unit first',
                'data' => null,
            ], SymfonyResponse::HTTP_BAD_REQUEST);
        }

        $unit->delete();

        return response()->json([
            'message' => 'Success deleting unit',
            'data' => $unit,
        ], SymfonyResponse::HTTP_OK);
    }
}
