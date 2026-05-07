<?php

namespace App\Http\Controllers\Dropdowns;

use App\Http\Controllers\Controller;
use App\Models\PhedDivision;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PhedDivisionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $divisions = PhedDivision::where('is_active', 1)
            ->when($request->circle_id, fn($query) => $query->where('circle_id', $request->circle_id))
            ->when($request->district_id, fn($query) => $query->where('district_id', $request->district_id))
            ->select('id', 'name', 'circle_id', 'district_id')
            ->get();

        return response()->json([
            'message' => 'Success fetching PHED divisions',
            'data' => $divisions
        ], SymfonyResponse::HTTP_OK);
    }
}
