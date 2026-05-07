<?php

namespace App\Http\Controllers\Assets;

use App\Enums\AssetMaintenanceStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Asset\StoreAssetMaintenanceLogRequest;
use App\Models\Asset\AssetMaintenanceSchedule;
use App\Models\AssetMaintenanceScheduleLog;
use App\Notifications\GenericNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AssetMaintenanceLogController extends Controller
{

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreAssetMaintenanceLogRequest $request
     * @return JsonResponse
     */
    public function store(StoreAssetMaintenanceLogRequest $request)
    {
        $validatedData = $request->validated();
        try {
            DB::beginTransaction();

            if ($request->has('file')) {
                $path = Storage::disk('public')->put('/assetMaintenanceLogs', $request->file);
                $validatedData = array_merge($validatedData, ['file' => $path]);
            }

            $assetMaintenanceLog = auth()->user()
                ->assetMaintenanceLogs()
                ->create($validatedData);

            AssetMaintenanceScheduleLog::query()
                ->find($request->id)
                ->update([
                    'status' => 'completed'
                ]).
            AssetMaintenanceSchedule::query()
                ->where('id', '=', $validatedData['asset_maintenance_schedule_id'])
                ->update(['status' => $validatedData['status']]);

            $schedule = AssetMaintenanceSchedule::query()
                ->where('id', '=', $validatedData['asset_maintenance_schedule_id'])->first();

            $assetName = $schedule->asset?->name;

            // notify user
            switch ($validatedData['status']) {
                case AssetMaintenanceStatusEnum::BROKEN->value:
                    $data = [
                        'content' => 'Your asset maintenance schedule with asset ' . $assetName . ' is broken',
                        'status' => AssetMaintenanceStatusEnum::BROKEN->value,
                        'asset_maintenance_schedule_id' => $validatedData['asset_maintenance_schedule_id'],
                    ];
                    break;
                case AssetMaintenanceStatusEnum::SERVICED->value:
                    $data = [
                        'content' => 'Your asset maintenance schedule with asset ' . $assetName . ' is serviced',
                        'status' => AssetMaintenanceStatusEnum::SERVICED->value,
                        'asset_maintenance_schedule_id' => $validatedData['asset_maintenance_schedule_id'],
                    ];
                    break;
                case AssetMaintenanceStatusEnum::DELAYED->value:
                    $data = [
                        'content' => 'Your asset maintenance schedule with asset ' . $assetName . ' is delayed',
                        'status' => AssetMaintenanceStatusEnum::DELAYED->value,
                        'asset_maintenance_schedule_id' => $validatedData['asset_maintenance_schedule_id'],
                    ];
                    break;
                case AssetMaintenanceStatusEnum::UNDER_SERVICE->value:
                    $data = [
                        'content' => 'Your asset maintenance schedule with asset ' . $assetName . ' is under-serviced',
                        'status' => AssetMaintenanceStatusEnum::UNDER_SERVICE->value,
                        'asset_maintenance_schedule_id' => $validatedData['asset_maintenance_schedule_id'],
                    ];
                    break;
            }
            auth()->user()->notify(new GenericNotification($data));

            DB::commit();
        } catch (\Exception $exception) {
            info($exception->getMessage());

            return response()->json([
                'message' => 'Error creating asset maintenance log',
                'data' => null,
            ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'message' => 'Success creating asset maintenance log',
            'data' => $assetMaintenanceLog
        ], SymfonyResponse::HTTP_CREATED);
    }

}
