<?php

namespace App\Http\Controllers\Dropdowns;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Division;
use App\Models\Province;
use App\Models\Tehsil;
use App\Models\UnionCouncil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class LocalityController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        $provinces = Province::query()
            ->get();

        $divisions = Division::query()
            ->get();

        $districts = District::query()
            ->get();

        $tehsils = Tehsil::query()
            ->get();

        $unionCouncils = UnionCouncil::query()
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
