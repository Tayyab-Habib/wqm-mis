<?php

namespace App\Http\Controllers\Dropdowns;

use App\Enums\AssetLogStatusEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AssetLogStatusController extends Controller
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
            'message' => 'Success fetching asset log statuses',
            'data' => AssetLogStatusEnum::array(),
        ], SymfonyResponse::HTTP_OK);
    }
}
