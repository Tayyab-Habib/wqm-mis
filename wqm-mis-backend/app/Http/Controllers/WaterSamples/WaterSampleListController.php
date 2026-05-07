<?php

namespace App\Http\Controllers\WaterSamples;

use App\Http\Controllers\Controller;
use App\Models\WaterSamples\WaterSample;
use App\Models\WaterScheme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;


class WaterSampleListController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function __invoke(Request $request,WaterScheme $waterScheme)
    {
        $waterSamples = $waterScheme->waterSamples()
        ->with([
            'waterScheme:id,name',
            'province:id,name',
            'division:id,name',
            'district:id,name',
            'tehsil:id,name',
            'unionCouncil:id,name',
            ])
        ->get();

        if (0 === $waterSamples->isEmpty()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => $waterScheme,
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success fetching water sample list',
            'data' => [
                'water_samples' => $waterSamples,
                'water_scheme' => $waterScheme->load([
                    'province:id,name',
                    'division:id,name',
                    'district:id,name',
                    'tehsil:id,name',
                ])
            ],
        ], SymfonyResponse::HTTP_OK);
    }
}
