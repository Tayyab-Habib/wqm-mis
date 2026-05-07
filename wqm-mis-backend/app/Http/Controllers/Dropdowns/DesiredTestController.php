<?php

namespace App\Http\Controllers\Dropdowns;

use App\Enums\DesiredTestEnum;
use App\Enums\ReasonForTestingEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DesiredTestController extends Controller
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
            'message' => 'Success fetching desired testing status',
            'data' => DesiredTestEnum::array(),
        ], SymfonyResponse::HTTP_OK);
    }
}
