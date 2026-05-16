<?php

namespace App\Http\Controllers\Dropdowns;

use App\Http\Controllers\Controller;
use App\Models\Laboratories\Laboratory;
use App\Services\AuthScope;
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
        // RBAC: scope to labs in user's hierarchy. SA/manager/view-only/general-view
        // see all; CE sees labs in their region; SE/XEN see lab for their circle;
        // lab roles see only their own lab(s) via the laboratory_user pivot.
        $query = Laboratory::query()
            ->select(['id', 'name', 'district_id', 'division_id'])
            ->isActive();
        AuthScope::labs($query, auth()->user());
        $laboratories = $query->get();

        return response()->json([
            'message' => 'Success fetching laboratories',
            'data' => $laboratories
        ], SymfonyResponse::HTTP_OK);
    }
}
