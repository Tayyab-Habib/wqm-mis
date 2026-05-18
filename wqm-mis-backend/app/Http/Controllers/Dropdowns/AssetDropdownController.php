<?php

namespace App\Http\Controllers\Dropdowns;

use App\Http\Controllers\Controller;
use App\Models\Asset\Asset;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Returns the full master assets catalog for dropdown use (Raise Demand
 * picker). Not lab-scoped — users need to be able to request equipment /
 * inventory items their lab doesn't currently hold.
 */
class AssetDropdownController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $assets = Asset::query()
            ->select(['id', 'name', 'kind', 'category', 'item_code', 'unit'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'Success fetching assets',
            'data' => $assets,
        ], SymfonyResponse::HTTP_OK);
    }
}
