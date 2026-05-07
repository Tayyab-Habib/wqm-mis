<?php

namespace App\Http\Controllers\Materials;

use App\Enums\MaterialLogStatusEnum;
use App\Enums\MaterialStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Material\StoreMaterialLogRequest;
use App\Http\Requests\Material\UpdateMaterialLogRequest;
use App\Models\Material\Material;
use App\Models\Material\MaterialLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class MaterialLogController extends Controller
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
     * @param StoreMaterialLogRequest $request
     * @return JsonResponse
     */
    public function store(StoreMaterialLogRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $materialLog = MaterialLog::query()
                ->create(array_merge($request->validated(), [
                    'user_id' => auth()->id(),
                    'date_of_entry' => now()->format('Y-m-d'),
                    'status' => MaterialLogStatusEnum::IN->value,
                ]));

            $materialLogsSum = MaterialLog::query()
                ->where('material_id', '=', $request->material_id)
                ->sum('quantity');

            $material = $materialLog->material;

            $material
                ->update([
                    'quantity' => $materialLogsSum,
                    'available_quantity' => $materialLogsSum,
                ]);

            $availableInventoryPercentage = $materialLogsSum / $material->quantity * 100;
            $status = $availableInventoryPercentage < $material->threshold
                ? MaterialStatusEnum::BELOW_THRESHOLD->value
                : MaterialStatusEnum::ACTIVE->value;

            $material->update(['status' => $status]);



            DB::commit();
            return response()->json([
                'message' => 'Success creating material stock',
                'data' => $materialLog
            ], SymfonyResponse::HTTP_CREATED);
        } catch (\Exception $exception) {
            info($exception->getMessage());
            DB::rollBack();
            return response()->json([
                'message' => 'Error updating material stock',
                'data' => null,
            ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);

        }
    }

    /**
     * Display the specified resource.
     *
     * @param MaterialLog $materialLog
     * @return JsonResponse
     */
    public function show(MaterialLog $materialLog): JsonResponse
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateMaterialLogRequest $request
     * @param MaterialLog $materialLog
     * @return JsonResponse
     */
    public function update(UpdateMaterialLogRequest $request, MaterialLog $materialLog): JsonResponse
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param MaterialLog $materialLog
     * @return JsonResponse
     */
    public function destroy(MaterialLog $materialLog): JsonResponse
    {
        //
    }
}
