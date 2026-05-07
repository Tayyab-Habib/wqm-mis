<?php

namespace App\Http\Controllers\Dropdowns;

use App\Enums\InvoiceableTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\StockableRequest;
use App\Http\Resources\LaboratoryAssetResource;
use App\Http\Resources\LaboratoryMaterialResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class StockableController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  StockableRequest
     * @return JsonResponse
     */
    public function __invoke(StockableRequest $request)
    {
        $validatedData = $request->validated();
        $laboratory = auth()->user()->laboratoryUser;

        switch ($validatedData['stockable_type']) {
            case InvoiceableTypeEnum::STOCK->value:
                $laboratoryMaterials = $laboratory->laboratoryMaterials()
                    ->with('material:id,name')
                    ->get();
                $stockable = (LaboratoryMaterialResource::collection($laboratoryMaterials));
                break;
            case InvoiceableTypeEnum::INVENTORY->value:
                $laboratoryAssets = $laboratory->laboratoryAssets()
                    ->with('asset:id,name')
                    ->get();

                $stockable = LaboratoryAssetResource::collection($laboratoryAssets);
                break;
        }

        return response()->json([
            'message' => 'Success fetching source types',
            'data' => $stockable,
        ], SymfonyResponse::HTTP_OK);
    }
}
