<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Asset\StoreEquipmentRepairLogRequest;
use App\Models\Asset\LaboratoryAsset;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EquipmentRepairLogController extends Controller
{
    /**
     * List repair logs for a specific laboratory asset.
     */
    public function index(LaboratoryAsset $laboratoryAsset): JsonResponse
    {
        $logs = $laboratoryAsset->repairLogs()->get([
            'id',
            'fault_date',
            'fault_description',
            'repair_status',
            'technician',
            'resolved_date',
            'repair_cost',
            'remarks',
            'created_at',
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

        // Create the repair log
        $log = $laboratoryAsset->repairLogs()->create($data);

        // Map repair status to equipment status
        $statusMap = [
            'Reported'     => 'Under Repair',
            'Under Repair' => 'Under Repair',
            'Resolved'     => 'Operational',
            'Beyond Repair'=> 'Out of Order',
        ];

        $newStatus = $statusMap[$data['repair_status']] ?? $data['repair_status'];
        $laboratoryAsset->update(['status' => $newStatus]);

        return response()->json([
            'message'          => 'Repair log saved successfully',
            'data'             => $log,
            'laboratory_asset' => $laboratoryAsset->only(['id', 'status']),
        ], SymfonyResponse::HTTP_CREATED);
    }
}
