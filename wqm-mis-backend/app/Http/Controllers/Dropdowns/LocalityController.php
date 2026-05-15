<?php

namespace App\Http\Controllers\Dropdowns;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Division;
use App\Models\Province;
use App\Models\Tehsil;
use App\Models\UnionCouncil;
use App\Services\AuthScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class LocalityController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * Returns all locality data needed for form cascades. Provinces are
     * never hierarchy-scoped (they're a higher level than regions). Divisions
     * + districts + their downstream tehsils/union_councils are scoped to
     * the user's hierarchy so forms only offer real choices.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = auth()->user();

        $provinces = Province::query()->get();

        $divQuery = Division::query();
        AuthScope::divisions($divQuery, $user);
        $divisions = $divQuery->get();

        $distQuery = District::query();
        AuthScope::districts($distQuery, $user);
        $districts = $distQuery->get();

        // Tehsils/Union Councils derive from the visible districts —
        // narrowing them keeps the cascade consistent for non-admin roles.
        $visibleDistrictIds = $districts->pluck('id');
        $tehsils = Tehsil::query()
            ->when($visibleDistrictIds->isNotEmpty(), fn($q) => $q->whereIn('district_id', $visibleDistrictIds))
            ->get();
        $visibleTehsilIds = $tehsils->pluck('id');
        $unionCouncils = UnionCouncil::query()
            ->when($visibleTehsilIds->isNotEmpty(), fn($q) => $q->whereIn('tehsil_id', $visibleTehsilIds))
            ->get();

        return response()->json([
            'message' => 'Success fetching locality',
            'data' => [
                'provinces' => $provinces,
                'divisions' => $divisions,
                'districts' => $districts,
                'tehsils' => $tehsils,
                'union_councils' => $unionCouncils,
            ],
        ], SymfonyResponse::HTTP_OK);

    }
}
