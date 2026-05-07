<?php

namespace App\Http\Controllers\Dropdowns;

use App\Enums\IssueTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseOrder\PurchasableRequest;
use App\Models\Asset\Asset;
use App\Models\Material\Material;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PurchasableController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(PurchasableRequest $request)
    {
        switch ($request->purchasable_type) {
            case IssueTypeEnum::INVENTORY->value:
                $query = Asset::query();
                break;

            case IssueTypeEnum::STOCK->value:
                $query = Material::query();
                break;
        }

        $purchasables = $query->select(['id', 'name', 'unit'])
            ->get();

        return response()->json([
            'message' => 'Success fetching purchasable',
            'data' => $purchasables,
        ], SymfonyResponse::HTTP_OK);
    }
}
