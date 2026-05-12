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
        // Per SRS §2.2 R-07: only active parameters, ordered by display_order.
        $tests = Test::query()
            ->select(['id', 'water_quality_parameter', 'type', 'unit', 'display_order'])
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('water_quality_parameter')
            ->get();

        return response()->json([
            'message' => 'Success fetching test parameters',
            'data' => $tests,
        ], SymfonyResponse::HTTP_OK);
    }
}
