<?php

namespace App\Http\Controllers\Dropdowns;

use App\Http\Controllers\Controller;
use App\Models\Region;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class RegionController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $regions = Region::where('is_active', 1)->select('id', 'name')->get();

        return response()->json([
            'message' => 'Success fetching regions',
            'data' => $regions
        ], SymfonyResponse::HTTP_OK);
    }
}
