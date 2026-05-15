<?php

namespace App\Http\Controllers\Dropdowns;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Services\AuthScope;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DivisionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = Division::query()
            ->when($request->region_id, fn($q) => $q->where('region_id', $request->region_id))
            ->select('id', 'name', 'region_id', 'province_id');
        AuthScope::divisions($query, auth()->user());
        $divisions = $query->get();

        return response()->json([
            'message' => 'Success fetching divisions',
            'data' => $divisions
        ], SymfonyResponse::HTTP_OK);
    }
}
