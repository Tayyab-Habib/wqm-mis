<?php

namespace App\Http\Controllers\Dropdowns;

use App\Enums\AssetStatusEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AssetStatusController extends Controller
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
            'message' => 'Success fetching asset statuses',
            'data' => AssetStatusEnum::array(),
        ], SymfonyResponse::HTTP_OK);
    }
}
