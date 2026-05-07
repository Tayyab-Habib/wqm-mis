<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Models\Asset\Asset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class UpdateAssetStatusController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param Asset $asset
     * @param boolean $isActive
     * @return JsonResponse
     */
    public function __invoke(Request $request, Asset $asset, bool $isActive)
    {
        $asset->update([
            'is_active' => $isActive,
            'modified_by' => auth()->id()
        ]);

        return response()->json([
            'message' => 'Success updating asset status',
            'data' => $asset,
        ], SymfonyResponse::HTTP_OK);
    }
}
