<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Asset\DeleteAssetMaintenanceScheduleRequest;
use App\Http\Requests\Asset\ShowAssetMaintenanceScheduleRequest;
use App\Http\Requests\Asset\StoreAssetMaintenanceScheduleRequest;
use App\Http\Requests\Asset\UpdateAssetMaintenanceScheduleRequest;
use App\Http\Requests\Asset\ViewAssetMaintenanceScheduleRequest;
use App\Models\Asset\AssetMaintenanceSchedule;
use App\Models\AssetMaintenanceScheduleLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AssetMaintenanceScheduleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(ViewAssetMaintenanceScheduleRequest $request)
    {
        $authUser = auth()->user();
        $assetMaintenanceSchedules = AssetMaintenanceScheduleLog::query()
            ->select([
                'asset_maintenance_schedule_logs.id',
                'asset_maintenance_schedule_logs.asset_ms_id',
                'asset_maintenance_schedule_logs.scheduled_at',
                'asset_maintenance_schedule_logs.status',
                'assets.name as asset_name',
                'laboratories.name as laboratory_name',
            ])
            ->leftJoin('laboratory_assets', 'asset_maintenance_schedule_logs.laboratory_asset_id', '=', 'laboratory_assets.id')
            ->leftJoin('assets', 'laboratory_assets.asset_id', '=', 'assets.id')
            ->leftJoin('laboratories', 'asset_maintenance_schedule_logs.laboratory_id', '=', 'laboratories.id')
            ->when(!$authUser->hasRole('system-administrator'), fn(Builder $query) => $query->where('asset_maintenance_schedule_logs.laboratory_id', '=', $authUser->laboratoryUser->id))
            ->get();

        if ($assetMaintenanceSchedules->isEmpty()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null,
            ], SymfonyResponse::HTTP_OK);
        }
        return response()->json([
            'message' => 'Success fetching asset maintenance schedules',
            'data' => $assetMaintenanceSchedules
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreAssetMaintenanceScheduleRequest $request
     * @return JsonResponse
     */
    public function store(StoreAssetMaintenanceScheduleRequest $request)
    {
        $validatedData = $request->validated();

        $assetMaintenanceSchedule = AssetMaintenanceSchedule::query()
            ->create($validatedData);

        return response()->json([
            'message' => 'Success creating asset maintenance schedule',
            'data' => $assetMaintenanceSchedule,
        ], SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     *
     * @param AssetMaintenanceSchedule $assetMaintenanceSchedule
     * @return JsonResponse
     */
    public function show(ShowAssetMaintenanceScheduleRequest $request, AssetMaintenanceSchedule $assetMaintenanceSchedule)
    {
        return response()->json([
            'message' => 'Success fetching asset maintenance schedule',
            'data' => $assetMaintenanceSchedule->load('assetMaintenanceLogs')
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateAssetMaintenanceScheduleRequest $request
     * @param AssetMaintenanceSchedule $assetMaintenanceSchedule
     * @return JsonResponse
     */
    public function update(UpdateAssetMaintenanceScheduleRequest $request, AssetMaintenanceSchedule $assetMaintenanceSchedule)
    {
        $assetMaintenanceSchedule->update($request->validated());

        if ($assetMaintenanceSchedule->wasChanged()) {
            return response()->json([
                'message' => 'Success updating asset maintenance schedule',
                'data' => $assetMaintenanceSchedule
            ]);
        }
        return response()->json([
            'message' => 'Error updating asset maintenance schedule'
        ], SymfonyResponse::HTTP_BAD_REQUEST);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param AssetMaintenanceSchedule $assetMaintenanceSchedule
     * @return JsonResponse
     */
    public function destroy(DeleteAssetMaintenanceScheduleRequest $request, AssetMaintenanceSchedule $assetMaintenanceSchedule)
    {
        $assetMaintenanceSchedule->delete();

        return response()->json([
            'message' => 'Success deleting asset maintenance schedule',
            'data' => null
        ], SymfonyResponse::HTTP_OK);
    }
}
