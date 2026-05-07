<?php

namespace App\Http\Controllers\PurchaseOrders;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseOrder\UpdatePurchaseOrderStatusRequest;
use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class UpdatePurchaseOrderStatusController extends Controller
{
    /**
     * Update the specified resource in storage.
     *
     * @param UpdatePurchaseOrderStatusRequest $request
     * @param PurchaseOrder $purchaseOrder
     * @return JsonResponse
     */
    public function update(UpdatePurchaseOrderStatusRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $validatedData = $request->validated();

        if ($purchaseOrder->status->value === $validatedData['status']) {
            return response()->json([
                'message' => 'purchase order status is already ' . $validatedData['status'],
                'data' => null
            ], SymfonyResponse::HTTP_BAD_REQUEST);
        }
        $purchaseOrder->update($validatedData);

        return response()->json([
            'message' => 'Success updating purchase order status',
            'data' => $purchaseOrder
        ]);
    }
}
