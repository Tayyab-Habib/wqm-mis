<?php

namespace App\Http\Controllers\Materials;

use App\Enums\MaterialLogStatusEnum;
use App\Enums\MaterialStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Material\StoreStockOutRequest;
use App\Models\Material\LaboratoryMaterial;
use App\Models\Material\Material;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class StockOutController extends Controller
{
    /**
     * Log a stock-out transaction.
     *
     * Writes to all 4 tables in one DB transaction:
     *   1. materials.available_quantity         -= quantity
     *   2. material_logs                         (new row, status=out)
     *   3. laboratory_materials.available_quantity -= quantity (for current lab)
     *   4. laboratory_material_logs              (new row, status=out, linked)
     *
     * Blocks the write if the lab's available_quantity < requested quantity.
     */
    public function store(StoreStockOutRequest $request): JsonResponse
    {
        $user  = auth()->user();
        $labId = $user->laboratoryDetails?->laboratory_id;

        if (!$labId) {
            return response()->json([
                'message' => 'Your account is not associated with a laboratory.',
                'data' => null,
            ], SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $qty = (float) $request->quantity;

        try {
            DB::beginTransaction();

            // Find the master material
            $material = Material::query()->findOrFail($request->material_id);

            // Find this lab's allocation row
            $labMaterial = LaboratoryMaterial::query()
                ->where('laboratory_id', $labId)
                ->where('material_id', $material->id)
                ->first();

            if (!$labMaterial) {
                DB::rollBack();
                return response()->json([
                    'message' => 'This material is not allocated to your laboratory.',
                    'errors'  => ['material_id' => ['No stock found for this item in your lab.']],
                    'data'    => null,
                ], SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Closing-balance constraint (SRS §2.7-4: block if approved qty > closing balance)
            $labAvailable = (float) $labMaterial->available_quantity;
            if ($qty > $labAvailable) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Insufficient stock. Available: ' . $labAvailable . ' ' . $labMaterial->unit,
                    'errors'  => ['quantity' => ['Requested quantity exceeds available stock (' . $labAvailable . ').']],
                    'data'    => null,
                ], SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
            }

            $date = $request->date ?: now()->format('Y-m-d');
            $extraMetadata = [
                'type'               => $request->type,
                'recipient_name'     => $request->recipient_name,
                'recipient_role'     => $request->recipient_role,
                'sample_ref'         => $request->sample_ref,
                'remarks'            => $request->remarks,
                'recipient_lab_id'   => $request->recipient_lab_id,
                'demand_id'          => $request->demand_id,
                'dispatch_reference' => $request->dispatch_reference,
            ];

            // 1. Decrement master
            $newMasterAvail = max(0, (float) $material->available_quantity - $qty);
            $material->update(['available_quantity' => $newMasterAvail]);
            $this->refreshMaterialStatus($material);

            // 2. Global ledger entry (status=out)
            $materialLog = $material->materialLogs()->create(array_merge([
                'user_id'        => $user->id,
                'date_of_expiry' => null,
                'quantity'       => $qty,
                'unit'           => $request->unit,
                'date_of_entry'  => $date,
                'status'         => MaterialLogStatusEnum::OUT->value,
            ], $extraMetadata));

            // 3. Decrement lab allocation (note: quantity column is VARCHAR)
            $labMaterial->update([
                'available_quantity' => $labAvailable - $qty,
            ]);

            // 4. Lab ledger entry, linked to global log
            $labMaterial->laboratoryMaterialLogs()->create(array_merge([
                'material_log_id' => $materialLog->id,
                'date_of_expiry'  => null,
                'quantity'        => (string) $qty,
                'unit'            => $request->unit,
                'status'          => MaterialLogStatusEnum::OUT->value,
            ], $extraMetadata));

            DB::commit();

            return response()->json([
                'message' => 'Stock-out logged successfully',
                'data'    => $materialLog->load('material'),
            ], SymfonyResponse::HTTP_CREATED);

        } catch (\Exception $exception) {
            DB::rollBack();
            info($exception->getMessage());
            return response()->json([
                'error'   => 'Error logging stock out',
                'message' => $exception->getMessage(),
                'data'    => null,
            ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Recompute the master material's RAG status from available_quantity vs threshold.
     */
    private function refreshMaterialStatus(Material $material): void
    {
        $available = (float) $material->available_quantity;
        $threshold = (float) $material->threshold;

        if ($available <= 0) {
            $status = MaterialStatusEnum::DEPLETED->value;
        } elseif ($threshold > 0 && $available < $threshold) {
            $status = MaterialStatusEnum::BELOW_THRESHOLD->value;
        } else {
            $status = MaterialStatusEnum::ACTIVE->value;
        }

        $material->update(['status' => $status]);
    }
}
