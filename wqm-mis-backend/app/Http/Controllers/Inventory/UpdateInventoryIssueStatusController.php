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
use App\Models\Material\LaboratoryMaterial;
use App\Models\Material\LaboratoryMaterialLog;
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

        if (!$inventoryable) {
            return response()->json([
                'message' => 'The item referenced by this demand no longer exists. It may have been deleted.',
                'data' => null,
            ], SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $authUser = auth()->user();
        $sourceLabId = $authUser->laboratoryDetails?->laboratory_id;

        if (!$sourceLabId) {
            return response()->json([
                'message' => 'Your account is not associated with a laboratory. Cannot issue stock.',
                'data' => null,
            ], SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        // SRS §2.7-4 constraint: block if source lab's closing balance is insufficient.
        $sourceLabAvailable = 0;
        if ($inventoryDetail->inventoryable_type === Material::class) {
            $sourceLabMaterial = LaboratoryMaterial::query()
                ->where('laboratory_id', $sourceLabId)
                ->where('material_id', $inventoryable->id)
                ->first();

            if (!$sourceLabMaterial) {
                return response()->json([
                    'message' => "Your lab has no allocation of [{$inventoryable->name}]. Cannot issue.",
                    'data' => null,
                ], SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Subtract expired-batch qty so we never issue from expired stock.
            // Frontend listing uses the same formula — keeps the validation honest.
            $expired = LaboratoryMaterialLog::query()
                ->where('laboratory_material_id', $sourceLabMaterial->id)
                ->where('status', 'in')
                ->whereNotNull('date_of_expiry')
                ->where('date_of_expiry', '<', now()->toDateString())
                ->sum('quantity');
            $sourceLabAvailable = max(0, (float) $sourceLabMaterial->available_quantity - (float) $expired);
        } else {
            // For Asset, fall back to master quantity for now.
            $sourceLabAvailable = (float) $inventoryable->quantity;
        }

        if ($request->quantity > $sourceLabAvailable) {
            return response()->json([
                'message' => "Insufficient (non-expired) stock for [{$inventoryable->name}] in your lab. Available: {$sourceLabAvailable}",
                'data' => null,
            ], SymfonyResponse::HTTP_BAD_REQUEST);
        }

        // Edge case: prevent issuing to your own lab (should be no-op).
        if ((int) $inventoryDetail->inventory->laboratory_id === (int) $sourceLabId) {
            return response()->json([
                'message' => 'Cannot issue stock to the same laboratory that owns it.',
                'data' => null,
            ], SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

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


                    $masterQty = $inventoryDetail->inventoryable->quantity ?? 0;
                    $availableInventoryPercentage = $masterQty > 0 ? ($materialLogsSum / $masterQty) * 100 : 0;
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

                    // Deduct from the SOURCE lab's allocation (Central Lab usually).
                    $this->deductMaterialFromSourceLab(
                        $sourceLabId,
                        $inventoryDetail['inventoryable_id'],
                        $request->quantity,
                        $materialLog,
                        $inventoryDetail
                    );

                    // Credit the RECEIVING lab (the lab that raised the demand).
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

            // Auto-spawn the un-fulfilled remainder as a new pending detail so the
            // requesting lab doesn't have to raise a fresh demand. The link to the
            // original is implicit via the same parent inventory.
            $remainder = (float) $inventoryDetail->quantity - (float) $request->quantity;
            if ($remainder > 0 && $validatedData['status'] === InventoryDetailStatusEnum::ISSUED->value) {
                InventoryDetail::query()->create([
                    'inventory_id'        => $inventoryDetail->inventory_id,
                    'inventoryable_id'    => $inventoryDetail->inventoryable_id,
                    'inventoryable_type'  => $inventoryDetail->inventoryable_type,
                    'quantity'            => $remainder,
                    'unit'                => $inventoryDetail->unit,
                    'status'              => InventoryDetailStatusEnum::PENDING->value,
                ]);
            }

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

    /**
     * Deduct issued quantity from the source lab's (e.g. Central Lab) own
     * laboratory_materials allocation and write an OUT entry to its log.
     * This is required by SRS §2.7-4 — the issuing lab's closing balance
     * must reflect the outflow.
     */
    private function deductMaterialFromSourceLab(int $sourceLabId, int $materialId, float $quantity, MaterialLog $materialLog, InventoryDetail $inventoryDetail): void
    {
        $sourceLabMaterial = LaboratoryMaterial::query()
            ->where('laboratory_id', $sourceLabId)
            ->where('material_id', $materialId)
            ->first();

        if (!$sourceLabMaterial) {
            // Pre-flight check should have caught this — fail loudly if it didn't.
            throw new \RuntimeException("Source lab {$sourceLabId} has no allocation of material {$materialId}.");
        }

        // Resolve recipient info from the parent inventory so the OUT log shows
        // *who* received the stock — without this the Stock Out table renders
        // Recipient Lab/Name/Role as dashes for Inter-lab Issuance rows.
        $inventory       = $inventoryDetail->inventory;
        $recipientLabId  = $inventory?->laboratory_id;
        $recipientUser   = $inventory?->createdByUser ?? \App\Models\User::find($inventory?->created_by);
        $recipientName   = $recipientUser?->name;
        $recipientRole   = $recipientUser?->roles?->first()?->name;

        // Write the OUT log first so the recomputed sum is consistent.
        $sourceLabMaterial->laboratoryMaterialLogs()->create([
            'material_log_id'  => $materialLog->id,
            'quantity'         => -$quantity,
            'date_of_expiry'   => $materialLog->date_of_expiry,
            'unit'             => $materialLog->unit,
            'status'           => MaterialLogStatusEnum::OUT->value,
            'type'             => 'inter_lab_issuance',
            'recipient_lab_id' => $recipientLabId,
            'recipient_name'   => $recipientName,
            'recipient_role'   => $recipientRole,
        ]);

        // Decrement available_quantity directly. We don't recompute from log
        // sums here because the existing allocation may pre-date the log
        // system (e.g. manually allocated rows have no IN log).
        $newAvail = max(0, (float) $sourceLabMaterial->available_quantity - $quantity);
        $sourceLabMaterial->update([
            'available_quantity' => $newAvail,
            'status' => $newAvail <= 0
                ? MaterialStatusEnum::DEPLETED->value
                : ($newAvail < (float) $sourceLabMaterial->threshold
                    ? MaterialStatusEnum::BELOW_THRESHOLD->value
                    : MaterialStatusEnum::ACTIVE->value),
        ]);
    }

    private function addMaterialToLaboratory(UpdateInventoryIssueStatusRequest $request, InventoryDetail $inventoryDetail, MaterialLog $materialLog)
    {
        $inventory = $inventoryDetail->inventory;
        $laboratory = $inventory->laboratory;

        $isNew = false;
        $laboratoryMaterial = $laboratory->laboratoryMaterials()
            ->where('material_id', $materialLog->material_id)
            ->first();

        if (!$laboratoryMaterial) {
            $laboratoryMaterial = $laboratory->laboratoryMaterials()->create([
                'material_id' => $materialLog->material_id,
                'quantity' => $request->quantity,
                'available_quantity' => $request->quantity,
                'unit' => $materialLog->unit,
                'threshold' => 0,
                'status' => MaterialStatusEnum::ACTIVE->value,
            ]);
            $isNew = true;
        }

        // Always write an IN log so future audits trace the receipt.
        $laboratoryMaterial->laboratoryMaterialLogs()->create([
            'material_log_id' => $materialLog->id,
            'quantity'        => $request->quantity,
            'date_of_expiry'  => $materialLog->date_of_expiry,
            'unit'            => $materialLog->unit,
            'status'          => MaterialLogStatusEnum::IN->value,
            'type'            => 'inter_lab_issuance',
        ]);

        // For an existing row, ADD to the current balance (the previous code
        // recomputed from log sums, which silently lost any pre-existing
        // allocation that had no IN log).
        if (!$isNew) {
            $newAvail = (float) $laboratoryMaterial->available_quantity + (float) $request->quantity;
            $newQty   = (float) $laboratoryMaterial->quantity + (float) $request->quantity;

            $threshold = (float) $laboratoryMaterial->threshold;
            $status = $newAvail <= 0
                ? MaterialStatusEnum::DEPLETED->value
                : ($threshold > 0 && $newAvail < $threshold
                    ? MaterialStatusEnum::BELOW_THRESHOLD->value
                    : MaterialStatusEnum::ACTIVE->value);

            $laboratoryMaterial->update([
                'quantity' => $newQty,
                'available_quantity' => $newAvail,
                'status' => $status,
            ]);
        }
    }
}
