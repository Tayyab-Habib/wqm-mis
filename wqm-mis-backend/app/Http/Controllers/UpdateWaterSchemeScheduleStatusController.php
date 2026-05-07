<?php

namespace App\Http\Controllers;

use App\Enums\StatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Asset\AssetMaintenanceSchedule;
use App\Models\WaterSchemeSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class UpdateWaterSchemeScheduleStatusController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JsonResponse
     */
    public function __invoke(Request $request, WaterSchemeSchedule $waterSchemeSchedule, $statusEnum): JsonResponse
    {
        $waterSchemeSchedule->update([
            'status' => $statusEnum
        ]);

        return response()->json([
            'message' => 'Success updating schedule status',
            'data' => null
        ], SymfonyResponse::HTTP_OK);
    }
}
