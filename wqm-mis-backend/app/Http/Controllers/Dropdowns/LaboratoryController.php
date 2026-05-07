<?php

namespace App\Http\Controllers\Dropdowns;

use App\Http\Controllers\Controller;
use App\Models\Laboratories\Laboratory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class LaboratoryController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        $authUser = auth()->user();
        $laboratories = Laboratory::query()
            ->select(['id', 'name', 'district_id', 'division_id'])
            ->isActive()
            ->when(!$authUser->hasRole('system-administrator'), fn($query) => $query->where('district_id', '=', $authUser->district_id))
            ->get();

        return response()->json([
            'message' => 'Success fetching laboratories',
            'data' => $laboratories
        ], SymfonyResponse::HTTP_OK);
    }
}
