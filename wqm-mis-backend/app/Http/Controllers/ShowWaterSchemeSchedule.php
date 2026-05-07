<?php

namespace App\Http\Controllers;

use App\Models\WaterScheme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ShowWaterSchemeSchedule extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JsonResponse
     */
    public function __invoke(Request $request, WaterScheme $waterScheme)
    {
        $waterScheme->load([
            'district',
            'division',
            'createdByUser:id,name',
            'modifiedByUser:id,name',
            'waterSchemeSchedules' => [
                'createdByUser:id,name',
                'modifiedByUser:id,name',
            ],
        ]);

        return response()->json([
            'message' => 'Success creating water schemes',
            'data' => $waterScheme,
        ], SymfonyResponse::HTTP_OK);
    }
}
