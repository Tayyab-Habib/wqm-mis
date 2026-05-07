<?php

namespace App\Http\Controllers\Assets;

use App\Enums\StatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Asset\AssetMaintenanceSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class UpdateAssetMaintenanceScheduleStatusController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JsonResponse
     */
    public function __invoke(Request $request, AssetMaintenanceSchedule $maintenanceSchedule, $statusEnum): JsonResponse
    {
        $maintenanceSchedule->update([
            'status' => $statusEnum
        ]);

        return response()->json([
            'message' => 'Success updating maintenance schedule',
            'data' => null
        ], SymfonyResponse::HTTP_OK);
    }
}
