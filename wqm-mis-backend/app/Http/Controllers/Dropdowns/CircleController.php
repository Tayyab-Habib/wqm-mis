<?php

namespace App\Http\Controllers\Dropdowns;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CircleController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $circles = Circle::where('is_active', 1)
            ->when($request->region_id, fn($query) => $query->where('region_id', $request->region_id))
            ->when($request->hub_lab_id, fn($query) => $query->where('hub_lab_id', $request->hub_lab_id))
            ->select('id', 'name', 'region_id', 'hub_lab_id')
            ->get();

        return response()->json([
            'message' => 'Success fetching circles',
            'data' => $circles
        ], SymfonyResponse::HTTP_OK);
    }
}
