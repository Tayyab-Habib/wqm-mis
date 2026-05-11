<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Asset\StoreEquipmentRepairLogRequest;
use App\Models\Asset\AssetMaintenanceLog;
use App\Models\Asset\LaboratoryAsset;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Repair history is now stored in asset_maintenance_logs with type='repair'
 * (migrated from equipment_repair_logs on 2026-05-11). API contract preserved
 * via column aliases.
 */
class EquipmentRepairLogController extends Controller
{
    /**
     * List repair logs for a specific laboratory asset.
     */
    public function index(LaboratoryAsset $laboratoryAsset): JsonResponse
    {
        $logs = AssetMaintenanceLog::query()
            ->where('laboratory_asset_id', $laboratoryAsset->id)
            ->where('type', 'repair')
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($log) => [
                'id'                => $log->id,
                'fault_date'        => $log->event_date,
                'fault_description' => $log->description,
                'repair_status'     => $log->result,
                'technician'        => $log->performer,
                'resolved_date'     => $log->resolved_date,
                'repair_cost'       => $log->cost,
                'remarks'           => $log->comment,
                'created_at'        => $log->created_at,
            ]);

        return response()->json([
            'message' => 'Success fetching repair logs',
            'data'    => $logs,
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a new repair log and update the equipment status accordingly.
     */
    public function store(StoreEquipmentRepairLogRequest $request): JsonResponse
    {
        $data = $request->validated();

        $laboratoryAsset = LaboratoryAsset::findOrFail($data['laboratory_asset_id']);

        $log = AssetMaintenanceLog::create([
            'laboratory_asset_id' => $laboratoryAsset->id,
            'user_id'             => auth()->id(),
            'type'                => 'repair',
            'event_date'          => $data['fault_date'] ?? null,
            'description'         => $data['fault_description'] ?? null,
            'reported_by'         => $data['reported_by'] ?? null,
            'result'              => $data['repair_status'] ?? null,
            'performer'           => $data['technician'] ?? null,
            'resolved_date'       => $data['resolved_date'] ?? null,
            'cost'                => $data['repair_cost'] ?? null,
            'comment'             => $data['remarks'] ?? null,
            'status'              => 'completed',
        ]);

        // Map repair status to equipment status
        $statusMap = [
            'Reported'      => 'Under Repair',
            'Under Repair'  => 'Under Repair',
            'Resolved'      => 'Operational',
            'Beyond Repair' => 'Out of Order',
        ];

        $newStatus = $statusMap[$data['repair_status'] ?? ''] ?? ($data['repair_status'] ?? null);
        if ($newStatus) {
            $laboratoryAsset->update(['status' => $newStatus]);
        }

        $logPayload = [
            'id'                => $log->id,
            'fault_date'        => $log->event_date,
            'fault_description' => $log->description,
            'repair_status'     => $log->result,
            'technician'        => $log->performer,
            'resolved_date'     => $log->resolved_date,
            'repair_cost'       => $log->cost,
            'remarks'           => $log->comment,
            'created_at'        => $log->created_at,
        ];

        return response()->json([
            'message'          => 'Repair log saved successfully',
            'data'             => $logPayload,
            'laboratory_asset' => $laboratoryAsset->only(['id', 'status']),
        ], SymfonyResponse::HTTP_CREATED);
    }
}
