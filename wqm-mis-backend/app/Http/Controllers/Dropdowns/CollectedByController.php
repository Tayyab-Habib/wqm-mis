<?php

namespace App\Http\Controllers\Dropdowns;

use App\Enums\CollectedByEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CollectedByController extends Controller
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
            'message' => 'Success fetching collected by statuses',
            'data' => CollectedByEnum::array(),
        ], SymfonyResponse::HTTP_OK);
    }
}
