<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardRequest;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;


class DistrictWiseContaminantsController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param DashboardRequest $request
     * @return JsonResponse
     */
    public function __invoke(DashboardRequest $request)
    {
        $dashboardService = new DashboardService($request);

        $districtWiseContaminantsCount = $dashboardService->getDistrictWiseContaminantsCount();

        return response()->json([
            'district_wise_contaminants_count' => $districtWiseContaminantsCount,
        ], SymfonyResponse::HTTP_OK);
    }
}
