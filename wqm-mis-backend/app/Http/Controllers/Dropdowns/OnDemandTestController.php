<?php

namespace App\Http\Controllers\Dropdowns;

use App\Enums\OnDemandTestEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class OnDemandTestController extends Controller
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
            'message' => 'Success fetching on demand tests',
            'data' => OnDemandTestEnum::array(),
        ], SymfonyResponse::HTTP_OK);
    }
}
