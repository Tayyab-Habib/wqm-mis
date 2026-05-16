<?php

namespace App\Http\Controllers\Dropdowns;

use App\Http\Controllers\Controller;
use App\Models\WaterScheme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class WaterSchemeController extends Controller
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
        $waterSchemes = WaterScheme::query()
            ->select(['id', 'name', 'latitude', 'longitude', 'district_id', 'tehsil_id'])
            ->isActive()
            ->when(!$authUser->isUnscoped(), fn($query) => $query->where('district_id', '=', $authUser->district_id))
            ->get();

        return response()->json([
            'message' => 'Success fetching laboratories',
            'data' => $waterSchemes
        ], SymfonyResponse::HTTP_OK);
    }
}
