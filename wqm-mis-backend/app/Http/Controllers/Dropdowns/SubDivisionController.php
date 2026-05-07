<?php

namespace App\Http\Controllers\Dropdowns;

use App\Http\Controllers\Controller;
use App\Models\SubDivision;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SubDivisionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $subDivisions = SubDivision::query()
            ->when($request->phed_division_id, fn($query) => $query->where('phed_division_id', $request->phed_division_id))
            ->select('id', 'name', 'phed_division_id')
            ->get();

        return response()->json([
            'message' => 'Success fetching sub divisions',
            'data' => $subDivisions
        ], SymfonyResponse::HTTP_OK);
    }
}
