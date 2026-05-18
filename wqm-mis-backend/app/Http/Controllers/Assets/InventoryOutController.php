<?php

namespace App\Http\Controllers\Assets;

use App\Enums\AssetLogStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Asset\StoreInventoryOutRequest;
use App\Models\Asset\Asset;
use App\Models\Asset\LaboratoryAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * SRS §2.7-2 — Logs a disposal event (Condemned, Missing/Lost, Transferred,
 * Disposed, Donated) for a non-consumable inventory item.
 *
 * Mirrors StockOutController. Writes all 4 tables in one transaction:
 *   1. assets.quantity                       -= quantity
 *   2. asset_logs                             (status=out + disposal metadata)
 *   3. laboratory_assets.quantity             -= quantity (for current lab)
 *   4. laboratory_asset_logs                  (status=out, linked)
 */
class InventoryOutController extends Controller
{
    public function store(StoreInventoryOutRequest $request): JsonResponse
    {
        $user  = auth()->user();
        $labId = $user->laboratoryDetails?->laboratory_id;

        if (!$labId) {
            return response()->json([
                'message' => 'Your account is not associated with a laboratory.',
                'data'    => null,
            ], SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $qty = (float) $request->quantity;

        try {
            DB::beginTransaction();

            $asset = Asset::query()->findOrFail($request->asset_id);

            $labAsset = LaboratoryAsset::query()
                ->where('laboratory_id', $labId)
                ->where('asset_id', $asset->id)
                ->first();

            if (!$labAsset) {
                DB::rollBack();
                return response()->json([
                    'message' => 'This asset is not allocated to your laboratory.',
                    'errors'  => ['asset_id' => ['No allocation found for this item in your lab.']],
                    'data'    => null,
                ], SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
            }

            $labAvailable = (float) $labAsset->quantity;
            if ($qty > $labAvailable) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Insufficient inventory. Available: ' . $labAvailable . ' ' . $labAsset->unit,
                    'errors'  => ['quantity' => ['Requested quantity exceeds available stock (' . $labAvailable . ').']],
                    'data'    => null,
                ], SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
            }

            $date = $request->date ?: now()->format('Y-m-d');
            $extraMetadata = [
                'type'               => $request->type,
                'recipient_name'     => $request->recipient_name,
                'recipient_role'     => $request->recipient_role,
                'asset_ref'          => $request->asset_ref,
                'remarks'            => $request->remarks,
                'recipient_lab_id'   => $request->recipient_lab_id,
                'dispatch_reference' => $request->dispatch_reference,
            ];

            $isTransfer = $request->type === \App\Enums\AssetDisposalTypeEnum::TRANSFERRED->value;

            // 1. Decrement master ONLY for true disposals — a transfer moves
            //    the asset between labs within the org so the org-wide count
            //    must not change.
            if (!$isTransfer) {
                $newMasterQty = max(0, (float) $asset->quantity - $qty);
                $asset->update(['quantity' => $newMasterQty]);
            }

            // 2. Global ledger entry (status=out + disposal metadata)
            $assetLog = $asset->assetLogs()->create(array_merge([
                'user_id'       => $user->id,
                'quantity'      => $qty,
                'unit'          => $request->unit,
                'date_of_entry' => $date,
                'status'        => AssetLogStatusEnum::OUT->value,
            ], $extraMetadata));

            // 3. Decrement source lab allocation
            $labAsset->update([
                'quantity' => (string) ($labAvailable - $qty),
            ]);

            // 4. Source lab ledger entry, linked to the global log
            $labAsset->laboratoryAssetLogs()->create(array_merge([
                'asset_log_id' => $assetLog->id,
                'quantity'     => (string) $qty,
                'unit'         => $request->unit,
                'status'       => AssetLogStatusEnum::OUT->value,
            ], $extraMetadata));

            // 5. (transfer only) Credit the destination lab — find-or-create
            //    its laboratory_assets row and append a status=in ledger entry
            //    so the receiving lab-incharge actually sees the inbound item
            //    in their Inventory Register and history. Without this step
            //    the asset evaporates from the receiver's perspective.
            if ($isTransfer && $request->recipient_lab_id) {
                $destLabAsset = LaboratoryAsset::query()
                    ->where('laboratory_id', $request->recipient_lab_id)
                    ->where('asset_id', $asset->id)
                    ->first();

                if ($destLabAsset) {
                    $destLabAsset->update([
                        'quantity' => (string) ((float) $destLabAsset->quantity + $qty),
                    ]);
                } else {
                    $destLabAsset = LaboratoryAsset::query()->create([
                        'laboratory_id' => $request->recipient_lab_id,
                        'asset_id'      => $asset->id,
                        'quantity'      => (string) $qty,
                        'unit'          => $request->unit,
                    ]);
                }

                // Destination ledger — record the inbound side of the transfer
                // pointing back at the source lab in recipient_lab_id (so the
                // receiver's trail can answer "where did this come from?").
                $destLabAsset->laboratoryAssetLogs()->create([
                    'asset_log_id'     => $assetLog->id,
                    'quantity'         => (string) $qty,
                    'unit'             => $request->unit,
                    'status'           => AssetLogStatusEnum::IN->value,
                    'type'             => $request->type,
                    'recipient_lab_id' => $labAsset->laboratory_id, // source lab
                    'remarks'          => $request->remarks
                        ? 'Received via transfer. ' . $request->remarks
                        : 'Received via transfer from source lab.',
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Inventory-out logged successfully',
                'data'    => $assetLog->load('asset'),
            ], SymfonyResponse::HTTP_CREATED);

        } catch (\Exception $exception) {
            DB::rollBack();
            info($exception->getMessage());
            return response()->json([
                'message' => $exception->getMessage(),
                'data'    => null,
            ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
