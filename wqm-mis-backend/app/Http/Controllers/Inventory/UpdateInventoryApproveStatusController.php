<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\InventoryDetailStatusEnum;
use App\Enums\InventoryStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\UpdateInventoryApproveStatusRequest;
use App\Models\Inventory\InventoryDetail;
use App\Models\Inventory\InventoryLog;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class UpdateInventoryApproveStatusController extends Controller
{
    /**
     * Update approve-status of the inventory-detail in storage.
     *
     * @param UpdateInventoryApproveStatusRequest $request
     * @param InventoryDetail $inventoryDetail
     * @return JsonResponse
     */
    public function update(UpdateInventoryApproveStatusRequest $request, InventoryDetail $inventoryDetail)
    {
        $validatedData = $request->validated();

        if ($inventoryDetail->status->value === $validatedData['status']) {
            return response()->json([
                'message' => 'Inventory-detail status is already ' . $validatedData['status'],
                'data' => null
            ], SymfonyResponse::HTTP_BAD_REQUEST);
        }

        if ($inventoryDetail->status->value === InventoryDetailStatusEnum::ISSUED->value) {
            return response()->json([
                'message' => 'Cannot reject inventory detail that is already issued',
                'data' => null
            ], SymfonyResponse::HTTP_BAD_REQUEST);
        }
        try {
            DB::beginTransaction();

            InventoryLog::query()->create([
                'user_id' => auth()->id(),
                'inventory_detail_id' => $inventoryDetail->id,
                'status' => $validatedData['status'],
                'comment' => $validatedData['comment'],
            ]);

            $inventoryDetail->update(['status' => $validatedData['status']]);

            $inventory = $inventoryDetail->inventory;

            $laboratoryName = $inventoryDetail->inventoryDetailLaboratory->name;

            $inventoryDetailCount = $inventory->inventoryDetails()->count();
            // notify user
            switch ($validatedData['status']) {
                case InventoryDetailStatusEnum::APPROVED->value:
                    $approvedInventoryDetailCount = $inventory->inventoryDetails()
                        ->where('status', '=', InventoryDetailStatusEnum::APPROVED->value)
                        ->count();

                    if ($approvedInventoryDetailCount < $inventoryDetailCount) {
                        $inventory->update([
                            'status' => InventoryStatusEnum::PARTIALLY_APPROVED->value
                        ]);
                    } elseif ($approvedInventoryDetailCount === $inventoryDetailCount) {
                        $inventory->update([
                            'status' => InventoryStatusEnum::APPROVED->value
                        ]);
                    }

                    $data = [
                        'content' => 'You have an approved inventory request from ' . $laboratoryName,
                        'status' => InventoryDetailStatusEnum::APPROVED->value,
                        'inventory_detail_id' => $inventoryDetail->id,
                    ];
                    break;
                case InventoryDetailStatusEnum::REJECTED->value:
                    $approvedInventoryDetailCount = $inventory->inventoryDetails()
                        ->where('status', '=', InventoryDetailStatusEnum::REJECTED->value)
                        ->count();

                    if ($approvedInventoryDetailCount === $inventoryDetailCount) {
                        $inventory->update([
                            'status' => InventoryStatusEnum::REJECTED->value
                        ]);
                    }
                    $data = [
                        'content' => 'You have a rejected inventory request from ' . $laboratoryName,
                        'status' => InventoryDetailStatusEnum::REJECTED->value,
                        'inventory_detail_id' => $inventoryDetail->id,
                    ];
                    break;
            }

            Notification::send(User::query()->find($inventory->created_by), new GenericNotification($data));

            DB::commit();

            if ($inventoryDetail->wasChanged()) {
                return response()->json([
                    'message' => 'Success updating inventory-detail status',
                    'data' => $inventory
                ]);
            }
        } catch (\Exception $exception) {
            DB::rollBack();
            info($exception->getMessage());
        }

        return response()->json([
            'message' => 'Error updating status of inventory-detail',
            'data' => null
        ], SymfonyResponse::HTTP_BAD_REQUEST);
    }
}
