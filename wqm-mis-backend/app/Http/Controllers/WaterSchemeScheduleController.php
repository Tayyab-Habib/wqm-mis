<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWaterSchemeScheduleRequest;
use App\Http\Requests\UpdateWaterSchemeScheduleRequest;
use App\Http\Requests\WaterScheme\UpdateWaterSchemeRequest;
use App\Models\WaterSchemeSchedule;
use App\Models\WaterSchemeScheduleLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class WaterSchemeScheduleController extends Controller
{
    public function index(): JsonResponse
    {
        $authUser = auth()->user();
        $waterSchemeScheduleLogs = WaterSchemeScheduleLog::query()
            ->select([
                'water_scheme_schedule_logs.id',
                'water_scheme_schedule_logs.scheduled_at',
                'water_scheme_schedule_logs.laboratory_id',
                'water_scheme_schedule_logs.status',
                'water_schemes.name as water_scheme_name',
                'laboratories.name as laboratory_name',
            ])
            ->leftJoin('water_schemes', 'water_scheme_schedule_logs.water_scheme_id', '=', 'water_schemes.id')
            ->leftJoin('laboratories', 'water_scheme_schedule_logs.laboratory_id', '=', 'laboratories.id')
            ->when(!$authUser->isUnscoped(), fn(Builder $query) => $query->where('water_scheme_schedule_logs.laboratory_id', '=', $authUser->laboratoryUser->id))
            ->get();

        if ($waterSchemeScheduleLogs->isEmpty()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null,
            ], SymfonyResponse::HTTP_OK);
        }
        return response()->json([
            'message' => 'Success fetching asset maintenance schedules',
            'data' => $waterSchemeScheduleLogs
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreWaterSchemeScheduleRequest $request
     * @return JsonResponse
     */
    public function store(StoreWaterSchemeScheduleRequest $request)
    {
        $waterSchemeSchedule = WaterSchemeSchedule::query()
            ->create(array_merge($request->validated(), [
                'laboratory_id' => auth()->user()->laboratoryUser->id
            ]));

        return response()->json([
            'message' => 'Success creating water schemes',
            'data' => $waterSchemeSchedule,
        ], SymfonyResponse::HTTP_CREATED);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param UpdateWaterSchemeScheduleRequest $request
     * @param WaterSchemeSchedule $waterSchemeSchedule
     * @return JsonResponse
     */
    public function update(UpdateWaterSchemeScheduleRequest $request, WaterSchemeSchedule $waterSchemeSchedule)
    {
        $waterSchemeSchedule->update($request->validated());

        return response()->json([
            'message' => 'Success updating water schemes',
            'data' => $waterSchemeSchedule,
        ], SymfonyResponse::HTTP_OK);
    }
}
