<?php

namespace App\Http\Controllers\Dropdowns;

use App\Http\Controllers\Controller;
use App\Models\Test;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class TestParameterController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        $tests = Test::query()
            ->select(['id', 'water_quality_parameter', 'type'])
            ->get();

        return response()->json([
            'message' => 'Success fetching test parameters',
            'data' => $tests,
        ], SymfonyResponse::HTTP_OK);
    }
}
