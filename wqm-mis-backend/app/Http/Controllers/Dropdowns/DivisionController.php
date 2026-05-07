<?php

namespace App\Http\Controllers\Dropdowns;

use App\Http\Controllers\Controller;
use App\Models\Division;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DivisionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $divisions = Division::query()
            ->when($request->region_id, fn($query) => $query->where('region_id', $request->region_id))
            ->select('id', 'name', 'region_id')
            ->get();

        return response()->json([
            'message' => 'Success fetching divisions',
            'data' => $divisions
        ], SymfonyResponse::HTTP_OK);
    }
}
