<?php

namespace App\Http\Controllers\Dropdowns;

use App\Enums\TestFrequencyEnum;
use App\Enums\TestTypeEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class TestTypeController extends Controller
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
            'message' => 'Success fetching test types',
            'data' => TestTypeEnum::array(),
        ], SymfonyResponse::HTTP_OK);
    }
}
