<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Http\Resources\LaboratoryAssetResource;
use App\Models\Asset\LaboratoryAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ShowAssetMaintenanceScheduleController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JsonResponse
     */
    public function __invoke(Request $request, LaboratoryAsset $laboratoryAsset): JsonResponse
    {
        return response()->json([
            'message' => 'success fetching asset maintenance schedules',
            'data' => (new LaboratoryAssetResource($laboratoryAsset->load(['asset:id,name']))),
        ], SymfonyResponse::HTTP_OK);
    }
}
