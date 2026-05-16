<?php

namespace App\Http\Controllers\Dropdowns;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Services\AuthScope;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DistrictController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = District::query()
            ->when($request->division_id, fn($q) => $q->where('division_id', $request->division_id))
            ->when($request->circle_id, fn($q) => $q->where('circle_id', $request->circle_id))
            ->select('id', 'name', 'division_id', 'circle_id', 'latitude', 'longitude');
        AuthScope::districts($query, auth()->user());
        $districts = $query->get();

        return response()->json([
            'message' => 'Success fetching districts',
            'data' => $districts
        ], SymfonyResponse::HTTP_OK);
    }
}
