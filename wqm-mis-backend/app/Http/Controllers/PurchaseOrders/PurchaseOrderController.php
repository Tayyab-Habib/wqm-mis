<?php

namespace App\Http\Controllers\PurchaseOrders;

use App\Enums\IssueTypeEnum;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseOrder\DeletePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\ShowPurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\StorePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\UpdatePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\ViewPurchaseOrderRequest;
use App\Http\Resources\PurchasableDetailResource;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\Asset\Asset;
use App\Models\Material\Material;
use PDF;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PurchaseOrderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(ViewPurchaseOrderRequest $request): JsonResponse
    {
        $purchaseOrders = PurchaseOrder::query()->get();

        if ($purchaseOrders->isEmpty()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success fetching purchase orders',
            'data' => $purchaseOrders
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StorePurchaseOrderRequest $request
     * @return JsonResponse
     */
    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();

            DB::beginTransaction();
            $purchaseOrder = PurchaseOrder::query()
                ->create(array_merge($validatedData, [
                    'date_of_order' => now()->format('Y-m-d')
                ]));

            foreach ($validatedData['details'] as $detail) {
                $purchasableType = match ($detail['purchasable_type']) {
                    IssueTypeEnum::STOCK->value => Material::class,
                    IssueTypeEnum::INVENTORY->value => Asset::class,
                };
                $purchaseOrderData = [
                    'purchasable_type' => $purchasableType,
                    'purchasable_id' => $detail['purchasable_id'],
                    'purchase_order_id' => $purchaseOrder->id,
                    'quantity' => $detail['quantity'],
                    'unit' => $detail['unit'],
                ];

                PurchaseOrderDetail::query()
                    ->create($purchaseOrderData);
            }
            DB::commit();
        } catch (\Exception $exception) {
            info($exception->getMessage());

            return response()->json([
                'message' => 'Error creating purchase Order',
                'data' => null,
            ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        return response()->json([
            'message' => 'Success creating purchase Order',
            'data' => $purchaseOrder,
        ], SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     *
     * @param PurchaseOrder $purchaseOrder
     * @return JsonResponse
     */
    public function show(ShowPurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder->load('purchaseOrderDetails.purchasable:id,name');

        return response()->json([
            'message' => 'Success fetching purchase order',
            'data' => (new PurchaseOrderResource($purchaseOrder)),
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdatePurchaseOrderRequest $request
     * @param PurchaseOrder $purchaseOrder
     * @return JsonResponse
     */
    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $validatedData = $request->validated();

        if ($purchaseOrder->status !== PurchaseOrderStatus::PENDING) {
            return response()->json([
                'message' => 'Error updating purchase order as it is in ' . $purchaseOrder->status->value . ' status',
                'data' => null
            ], SymfonyResponse::HTTP_BAD_REQUEST);
        }

        try {
            foreach ($validatedData['details'] as $detail) {
                $purchasableType = match ($detail['purchasable_type']) {
                    IssueTypeEnum::STOCK->value => Material::class,
                    IssueTypeEnum::INVENTORY->value => Asset::class,
                };

                $purchaseOrderData[] = [
                    'purchasable_type' => $purchasableType,
                    'purchasable_id' => $detail['purchasable_id'],
                    'purchase_order_id' => $purchaseOrder->id,
                    'quantity' => $detail['quantity'],
                    'unit' => $detail['unit'],
                ];
            }


            DB::beginTransaction();
            $purchaseOrder->update($request->validated());
            $purchaseOrder->purchaseOrderDetails()->delete();
            $purchaseOrder->purchaseOrderDetails()->insert($purchaseOrderData);

            DB::commit();

            return response()->json([
                'message' => 'Success updating purchase order',
                'data' => $purchaseOrder->load('purchaseOrderDetails')
            ]);

        } catch (\Exception $exception) {
            DB::rollBack();
            info($exception->getMessage());

            return response()->json([
                'message' => 'Error updating purchase order',
                'data' => null
            ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param PurchaseOrder $purchaseOrder
     * @return JsonResponse
     */
    public function destroy(DeletePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        if ($purchaseOrder->status !== PurchaseOrderStatus::PENDING) {
            return response()->json([
                'message' => 'Error deleting purchase order as it is in ' . $purchaseOrder->status->value . ' status',
                'data' => null
            ], SymfonyResponse::HTTP_BAD_REQUEST);
        }

        try {
            DB::beginTransaction();

            $purchaseOrder->purchaseOrderDetails()
                ->delete();

            $purchaseOrder->delete();
            DB::commit();
        } catch (\Exception $exception) {
            info($exception->getMessage());

            return response()->json([
                'message' => 'Error deleting purchase Order',
                'data' => null,
            ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        return response()->json([
            'message' => 'Success deleting purchase Order',
            'data' => $purchaseOrder,
        ], SymfonyResponse::HTTP_OK);
    }
    public function generatePdf(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load('purchaseOrderDetails.purchasable:id,name');

        $pdf = PDF::loadView('waterSample.purchaseOrder', compact('purchaseOrder'));

        $pdf->setOption('page-size', 'A4');
    
        return $pdf->download('purchase-order.pdf');
    }
}