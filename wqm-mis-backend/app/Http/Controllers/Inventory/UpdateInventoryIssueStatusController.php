<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\AssetLogStatusEnum;
use App\Enums\AssetStatusEnum;
use App\Enums\InventoryDetailStatusEnum;
use App\Enums\InventoryStatusEnum;
use App\Enums\MaterialLogStatusEnum;
use App\Enums\MaterialStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\UpdateInventoryIssueStatusRequest;
use App\Models\Asset\Asset;
use App\Models\Asset\AssetLog;
use App\Models\Inventory\InventoryDetail;
use App\Models\Inventory\InventoryLog;
use App\Models\Material\Material;
use App\Models\Material\MaterialLog;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class UpdateInventoryIssueStatusController extends Controller
{
    /**
     * Update issue-status of the inventory-detail in storage.
     *
     * @param UpdateInventoryIssueStatusRequest $request
     * @param InventoryDetail $inventoryDetail
     * @return JsonResponse
     */
    public function update(UpdateInventoryIssueStatusRequest $request, InventoryDetail $inventoryDetail)
    {
        $validatedData = $request->validated();

        if ($inventoryDetail->status->value === InventoryDetailStatusEnum::ISSUED->value) {
            return response()->json([
                'message' => 'Inventory-detail status is already ' . $validatedData['status'],
                'data' => null
            ], SymfonyResponse::HTTP_BAD_REQUEST);
        }

        $inventoryable = $inventoryDetail->inventoryable;

        $availableQuantity = ($inventoryDetail->inventoryable_type === Asset::class)
            ? $inventoryable->quantity
            : $inventoryable->available_quantity;

        if ($request->quantity > $availableQuantity) {
            return response()->json([
                'message' => "The selected quantity of [{$inventoryable->name}] is not available in inventory",
                'data' => null,
            ], SymfonyResponse::HTTP_BAD_REQUEST);
        }

        $authUser = auth()->user();

        try {
            DB::beginTransaction();

            $inventoryDetail->update(['approved_quantity' => $request->quantity]);


            // insert a new record with 'out' status in material/asset Log.
            $inventoryLogData = [
                'user_id' => $authUser->id,
                'quantity' => -($request->quantity),
                'unit' => $inventoryDetail['unit'],
                'date_of_entry' => now()->format('Y-m-d'),
                'status' => AssetLogStatusEnum::OUT->value,
            ];

            switch ($inventoryDetail->inventoryable_type) {
                case Asset::class:
                    $inventoryLogData = array_merge($inventoryLogData, [
                        'asset_id' => $inventoryDetail['inventoryable_id'],
                    ]);
                    $assetLog = AssetLog::query()->create($inventoryLogData);

                    $assetsLogsSum = AssetLog::query()
                        ->where('asset_id', '=', $inventoryDetail['inventoryable_id'])
                        ->sum('quantity');

//                    $status = $assetsLogsSum < $inventoryDetail->inventoryable->threshold
//                        ? AssetStatusEnum::BELOW_THRESHOLD->value
//                        : AssetStatusEnum::ACTIVE->value;
//
//
//                    if ($status === AssetStatusEnum::BELOW_THRESHOLD->value) {
//                        $data = [
//                            'content' => 'You have a below threshold asset ' . $assetLog->asset->name,
//                            'status' => AssetStatusEnum::BELOW_THRESHOLD->value,
//                            'name' => auth()->user()->name,
//                        ];
//                        //send notification to authenticated user
//                        auth()->user()->notify(new GenericNotification($data));
//                    }

                    Asset::query()
                        ->find($inventoryDetail['inventoryable_id'])
                        ->update([
                            'quantity' => $assetsLogsSum,
//                            'status' => $status
                        ]);

                    $this->addAssetToLaboratory($request, $inventoryDetail, $assetLog);

                    break;
                case Material::class:
                    $inventoryLogData = array_merge($inventoryLogData, [
                        'material_id' => $inventoryDetail['inventoryable_id'],
                        'date_of_expiry' => $inventoryDetail['date_of_expiry'],
                    ]);
                    $materialLog = MaterialLog::query()->create($inventoryLogData);

                    $materialLogsSum = MaterialLog::query()
                        ->where('material_id', '=', $inventoryDetail['inventoryable_id'])
                        ->sum('quantity');


                    $availableInventoryPercentage = $materialLogsSum / $inventoryDetail->inventoryable->quantity * 100;
                    $status = $availableInventoryPercentage < $inventoryDetail->inventoryable->threshold
                        ? MaterialStatusEnum::BELOW_THRESHOLD->value
                        : MaterialStatusEnum::ACTIVE->value;

                    if ($status === MaterialStatusEnum::BELOW_THRESHOLD->value) {
                        $data = [
                            'content' => 'You have a below threshold material ' . $materialLog->material->name,
                            'status' => MaterialStatusEnum::BELOW_THRESHOLD->value,
                            'name' => auth()->user()->name,
                        ];

                        //send notification to authenticated user
                        auth()->user()->notify(new GenericNotification($data));
                    }

                    Material::query()
                        ->find($inventoryDetail['inventoryable_id'])
                        ->update([
                            'available_quantity' => $materialLogsSum,
                            'status' => $status
                        ]);

                    $this->addMaterialToLaboratory($request, $inventoryDetail, $materialLog);
                    break;
            }
            InventoryLog::query()->create([
                'user_id' => auth()->id(),
                'inventory_detail_id' => $inventoryDetail->id,
                'status' => $validatedData['status'],
                'comment' => $validatedData['comment'],
            ]);
            $inventoryDetail->update(['status' => $validatedData['status']]);

            $inventory = $inventoryDetail->inventory;

            DB::enableQueryLog();

            $approvedInventoryDetailCount = $inventory->inventoryDetails()
                ->where(function ($query) {
                    $query->where('status', '=', InventoryDetailStatusEnum::APPROVED->value)
                        ->orWhere('status', '=', InventoryDetailStatusEnum::ISSUED->value);
                })
                ->count();

            $inventoryDetailCount = $inventory->inventoryDetails()->count();

            if ($approvedInventoryDetailCount < $inventoryDetailCount) {
                $inventory->update([
                    'status' => InventoryStatusEnum::PARTIALLY_APPROVED->value
                ]);
            } elseif ($approvedInventoryDetailCount === $inventoryDetailCount) {
                $inventory->update([
                    'status' => InventoryStatusEnum::APPROVED->value
                ]);
            }

            // notify user
            $data = [
                'name' => $authUser->name,
                'content' => 'Your requested inventory has been issued.',
                'status' => InventoryDetailStatusEnum::ISSUED->value,
            ];

            Notification::send(User::query()->find($inventory->created_by), new GenericNotification($data));


            DB::commit();

            return response()->json([
                'message' => 'Success updating status of inventory-detail',
                'data' => $inventory
            ]);
        } catch (\Exception $exception) {
            DB::rollBack();
            info($exception->getMessage());
        }

        return response()->json([
            'message' => 'Error updating status of inventory-detail',
            'data' => null,
        ], SymfonyResponse::HTTP_BAD_REQUEST);
    }

    private function addAssetToLaboratory(UpdateInventoryIssueStatusRequest $request, InventoryDetail $inventoryDetail, AssetLog $assetLog)
    {
        $inventory = $inventoryDetail->inventory;
        $laboratory = $inventory->laboratory;
        $laboratoryAsset = $laboratory->laboratoryAssets()
            ->firstOrCreate([
                'asset_id' => $assetLog->asset_id,
            ], [
                'quantity' => $request->quantity,
                'unit' => $assetLog->unit,
                'date_of_expiry' => $assetLog->date_of_expiry,
//                'threshold' => 0,
                'status' => AssetStatusEnum::ACTIVE,
            ]);

        $laboratoryAsset->laboratoryAssetLogs()
            ->create([
                'asset_log_id' => $assetLog->id,
                'quantity' => $request->quantity,
                'unit' => $assetLog->unit,
                'status' => AssetLogStatusEnum::IN,
            ]);

        $sumLaboratoryAssetLogs = $laboratoryAsset->laboratoryAssetLogs()
            ->sum('quantity');

        $laboratoryAsset->update(['quantity' => $sumLaboratoryAssetLogs]);

    }

    private function addMaterialToLaboratory(UpdateInventoryIssueStatusRequest $request, InventoryDetail $inventoryDetail, MaterialLog $materialLog)
    {
        $inventory = $inventoryDetail->inventory;
        $laboratory = $inventory->laboratory;
        $laboratoryMaterial = $laboratory->laboratoryMaterials()
            ->firstOrCreate([
                'material_id' => $materialLog->material_id,
            ], [
                'quantity' => $request->quantity,
                'available_quantity' => $request->quantity,
                'unit' => $materialLog->unit,
                'threshold' => 0,
                'status' => MaterialStatusEnum::ACTIVE,
            ]);

        $laboratoryMaterial->laboratoryMaterialLogs()
            ->create([
                'material_log_id' => $materialLog->id,
                'quantity' => $request->quantity,
                'date_of_expiry' => $materialLog->date_of_expiry,
                'unit' => $materialLog->unit,
                'status' => MaterialLogStatusEnum::IN,
            ]);

        $sumLaboratoryMaterialLogs = $laboratoryMaterial->laboratoryMaterialLogs()
            ->sum('quantity');

        $availableInventoryPercentage = $sumLaboratoryMaterialLogs / $inventoryDetail->inventoryable->quantity * 100;
        $status = $availableInventoryPercentage < $inventoryDetail->inventoryable->threshold
            ? MaterialStatusEnum::BELOW_THRESHOLD->value
            : MaterialStatusEnum::ACTIVE->value;

        $laboratoryMaterial->update([
            'quantity' => $sumLaboratoryMaterialLogs,
            'available_quantity' => $sumLaboratoryMaterialLogs,
            'status' => $status,
        ]);

    }
}
