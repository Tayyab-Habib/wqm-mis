<?php

namespace App\Http\Controllers\Dropdowns;

use App\Http\Controllers\Controller;
use App\Models\District;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DistrictController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $districts = District::query()
            ->when($request->division_id, fn($query) => $query->where('division_id', $request->division_id))
            ->when($request->circle_id, fn($query) => $query->where('circle_id', $request->circle_id))
            ->select('id', 'name', 'division_id', 'circle_id', 'latitude', 'longitude')
            ->get();

        return response()->json([
            'message' => 'Success fetching districts',
            'data' => $districts
        ], SymfonyResponse::HTTP_OK);
    }
}
