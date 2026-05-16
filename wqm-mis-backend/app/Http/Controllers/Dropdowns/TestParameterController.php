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
        // ASR needs the WHO/lab guideline columns for client-side exceedance highlighting.
        $tests = Test::query()
            ->select([
                'id', 'water_quality_parameter', 'type', 'unit',
                'permissible_limits', 'criteria', 'display_order', 'is_active',
                'who_guideline_start', 'who_guideline_end',
                'laboratory_guideline_start', 'laboratory_guideline_end',
            ])
            ->where('is_active', true)
            ->orderByRaw('COALESCE(display_order, 999999)')
            ->orderBy('water_quality_parameter')
            ->get();

        return response()->json([
            'message' => 'Success fetching test parameters',
            'data' => $tests,
        ], SymfonyResponse::HTTP_OK);
    }
}
