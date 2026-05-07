<?php

namespace App\Http\Controllers;

use App\Models\WaterScheme;
use App\Models\WaterSchemeSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class UpdateWaterSchemeStatusController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param WaterScheme $waterScheme
     * @param boolean $isActive
     * @return JsonResponse
     */
    public function __invoke(Request $request, WaterScheme $waterScheme, bool $isActive)
    {
        $waterScheme->update([
            'is_active' => $isActive,
            'modified_by' => auth()->id()
        ]);

        return response()->json([
            'message' => 'Success updating water schemes status',
            'data' => $waterScheme,
        ], SymfonyResponse::HTTP_OK);
    }
}
