<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Asset\StoreEquipmentCalibrationLogRequest;
use App\Models\Asset\AssetMaintenanceLog;
use App\Models\Asset\LaboratoryAsset;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Calibration history is now stored in asset_maintenance_logs with
 * type='calibration' (migrated from equipment_calibration_logs on 2026-05-11).
 *
 * The API contract is preserved by aliasing internal column names back to the
 * legacy field names (calibration_date / calibrated_by / certificate_ref ...)
 * so the frontend keeps working without changes.
 */
class EquipmentCalibrationLogController extends Controller
{
    /**
     * List calibration logs for a specific laboratory asset.
     */
    public function index(LaboratoryAsset $laboratoryAsset): JsonResponse
    {
        $logs = AssetMaintenanceLog::query()
            ->where('laboratory_asset_id', $laboratoryAsset->id)
            ->where('type', 'calibration')
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($log) => [
                'id'                    => $log->id,
                'calibration_date'      => $log->event_date,
                'calibrated_by'         => $log->performer,
                'result'                => $log->result,
                'certificate_ref'       => $log->ref_number,
                'standard_used'         => $log->standard_used,
                'next_due_date'         => $log->next_due_date,
                'remarks'               => $log->comment,
                'created_at'            => $log->created_at,
            ]);

        return response()->json([
            'message' => 'Success fetching calibration logs',
            'data'    => $logs,
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a new calibration log and update the equipment's next calibration date.
     */
    public function store(StoreEquipmentCalibrationLogRequest $request): JsonResponse
    {
        $data = $request->validated();

        $laboratoryAsset = LaboratoryAsset::findOrFail($data['laboratory_asset_id']);

        // Translate the API's legacy field names to the unified-log columns.
        $log = AssetMaintenanceLog::create([
            'laboratory_asset_id' => $laboratoryAsset->id,
            'user_id'             => auth()->id(),
            'type'                => 'calibration',
            'event_date'          => $data['calibration_date'] ?? null,
            'performer'           => $data['calibrated_by'] ?? null,
            'result'              => $data['result'] ?? null,
            'ref_number'          => $data['certificate_ref'] ?? null,
            'standard_used'       => $data['standard_used'] ?? null,
            'next_due_date'       => $data['next_due_date'] ?? null,
            'comment'             => $data['remarks'] ?? null,
            'status'              => 'completed',
        ]);

        // Update the laboratory asset's next_calibration_date and status.
        $updateFields = [];

        if (!empty($data['next_due_date'])) {
            $updateFields['next_calibration_date'] = $data['next_due_date'];
        }

        // Map calibration result to equipment status
        if (($data['result'] ?? null) === 'Pass') {
            $updateFields['status'] = 'Operational';
        } elseif (($data['result'] ?? null) === 'Fail') {
            $updateFields['status'] = 'Under Repair';
        }
        // 'Conditional Pass' → leave status unchanged

        if (!empty($updateFields)) {
            $laboratoryAsset->update($updateFields);
        }

        // Return the log shaped like the legacy response so the frontend
        // doesn't need to know about the column rename.
        $logPayload = [
            'id'                    => $log->id,
            'calibration_date'      => $log->event_date,
            'calibrated_by'         => $log->performer,
            'result'                => $log->result,
            'certificate_ref'       => $log->ref_number,
            'standard_used'         => $log->standard_used,
            'next_due_date'         => $log->next_due_date,
            'remarks'               => $log->comment,
            'created_at'            => $log->created_at,
        ];

        return response()->json([
            'message'          => 'Calibration log saved successfully',
            'data'             => $logPayload,
            'laboratory_asset' => $laboratoryAsset->only([
                'id', 'status', 'next_calibration_date',
            ]),
        ], SymfonyResponse::HTTP_CREATED);
    }
}
