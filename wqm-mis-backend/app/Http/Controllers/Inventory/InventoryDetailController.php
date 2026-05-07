<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\InventoryDetailStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\DeleteInventoryDetailRequest;
use App\Http\Requests\Inventory\ShowInventoryDetailRequest;
use App\Http\Requests\Inventory\UpdateInventoryDetailRequest;
use App\Models\Inventory\InventoryDetail;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class InventoryDetailController extends Controller
{
    /**
     * Display the specified resource.
     *
     * @param ShowInventoryDetailRequest $request
     * @param InventoryDetail $inventoryDetail
     * @return JsonResponse
     */
    public function show(ShowInventoryDetailRequest $request, InventoryDetail $inventoryDetail)
    {
        return response()->json([
            'message' => 'Success showing inventory',
            'data' => $inventoryDetail->load('inventory')
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Update quantity and unit of the specified resource in storage.
     *
     * @param UpdateInventoryDetailRequest $request
     * @param InventoryDetail $inventoryDetail
     * @return JsonResponse
     */
    public function update(UpdateInventoryDetailRequest $request, InventoryDetail $inventoryDetail)
    {
        if ($inventoryDetail->status !== InventoryDetailStatusEnum::PENDING) {
            return response()->json([
                'message' => 'Error updating inventory-detail',
                'data' => null
            ], SymfonyResponse::HTTP_BAD_REQUEST);
        }

        $inventoryDetail->update($request->validated());

        if ($inventoryDetail->wasChanged()) {
            return response()->json([
                'message' => 'Success updating inventory-detail',
                'data' => $inventoryDetail
            ]);
        }

        return response()->json([
            'message' => 'Error updating inventory-detail',
            'data' => null
        ], SymfonyResponse::HTTP_BAD_REQUEST);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DeleteInventoryDetailRequest $request
     * @param InventoryDetail $inventoryDetail
     * @return JsonResponse
     */
    public function destroy(DeleteInventoryDetailRequest $request, InventoryDetail $inventoryDetail)
    {
        $inventoryDetail->delete();

        return response()->json([
            'message' => 'Success deleting inventory-detail',
            'data' => null
        ], SymfonyResponse::HTTP_OK);
    }
}
