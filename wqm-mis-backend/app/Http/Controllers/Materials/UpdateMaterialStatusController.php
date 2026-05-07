<?php

namespace App\Http\Controllers\Materials;

use App\Http\Controllers\Controller;
use App\Models\Material\Material;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class UpdateMaterialStatusController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param Material $material
     * @param boolean $isActive
     * @return JsonResponse
     */
    public function __invoke(Request $request, Material $material, bool $isActive)
    {
        $material->update([
            'is_active' => $isActive,
            'modified_by' => auth()->id()
        ]);

        return response()->json([
            'message' => 'Success updating material status',
            'data' => $material,
        ], SymfonyResponse::HTTP_OK);
    }
}
