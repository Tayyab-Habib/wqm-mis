<?php

namespace App\Http\Controllers\Dropdowns;

use App\Enums\AssetMaintenanceStatusEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AssetMaintenanceStatusController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Success fetching asset maintenance statuses',
            'data' => AssetMaintenanceStatusEnum::array(),
        ], SymfonyResponse::HTTP_OK);
    }
}
