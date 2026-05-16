<?php

namespace App\Http\Controllers;

use App\Models\WaterScheme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WaterSchemeTestingStatusController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        $authUser = auth()->user();

        $query = WaterScheme::query()
            ->select(['id','slug', 'name', 'district_id', 'tehsil_id', 'created_at'])
            ->withExists('lastWaterSchemeSchedules as has_schedule')
            ->with([
                'lastWaterSchemeSchedules',
                'district:id,name',
                'tehsil:id,name'
            ]);

        if (!$authUser->isUnscoped()) {
            $query->where('district_id', '=', $authUser->district_id);
        }

        $waterSchemes = $query->get();

        return response()->json([
            'data' => $waterSchemes,
            'message' => 'Success fetching water schemes'
        ], Response::HTTP_OK);
    }
}
