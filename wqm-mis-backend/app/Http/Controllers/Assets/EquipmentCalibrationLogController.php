<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Asset\StoreEquipmentCalibrationLogRequest;
use App\Models\Asset\LaboratoryAsset;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EquipmentCalibrationLogController extends Controller
{
    /**
     * List calibration logs for a specific laboratory asset.
     */
    public function index(LaboratoryAsset $laboratoryAsset): JsonResponse
    {
        $logs = $laboratoryAsset->calibrationLogs()->get([
            'id',
            'calibration_date',
            'calibrated_by',
            'result',
            'certificate_ref',
            'standard_used',
            'next_due_date',
            'remarks',
            'created_at',
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

        // Create the calibration log
        $log = $laboratoryAsset->calibrationLogs()->create($data);

        // Update the laboratory asset's next_calibration_date and status
        $updateFields = [];

        if (!empty($data['next_due_date'])) {
            $updateFields['next_calibration_date'] = $data['next_due_date'];
        }

        // Map calibration result to equipment status
        if ($data['result'] === 'Pass') {
            $updateFields['status'] = 'Operational';
        } elseif ($data['result'] === 'Fail') {
            $updateFields['status'] = 'Under Repair';
        }
        // 'Conditional Pass' → leave status unchanged

        if (!empty($updateFields)) {
            $laboratoryAsset->update($updateFields);
        }

        return response()->json([
            'message'          => 'Calibration log saved successfully',
            'data'             => $log,
            'laboratory_asset' => $laboratoryAsset->only([
                'id', 'status', 'next_calibration_date',
            ]),
        ], SymfonyResponse::HTTP_CREATED);
    }
}
