<?php

namespace App\Http\Controllers\Dropdowns;

use App\Http\Controllers\Controller;
use App\Models\Material\Material;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Returns the full master materials catalog for dropdown use (e.g. Raise
 * Demand picker). Not lab-scoped — users need to be able to request items
 * their lab doesn't currently stock.
 */
class MaterialDropdownController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $materials = Material::query()
            ->select(['id', 'name', 'unit', 'category'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'Success fetching materials',
            'data' => $materials,
        ], SymfonyResponse::HTTP_OK);
    }
}
