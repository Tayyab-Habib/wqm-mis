<?php

namespace App\Http\Controllers\Dropdowns;

use App\Enums\IssueTypeEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PurchasableTypeController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request)
    {
        $purchasable = [
            IssueTypeEnum::STOCK->value => IssueTypeEnum::STOCK->name,
            IssueTypeEnum::INVENTORY->value =>IssueTypeEnum::INVENTORY->name
        ];

        return response()->json([
            'message' => 'Success fetching purchasable types',
            'data' => $purchasable,
        ], SymfonyResponse::HTTP_OK);

    }
}
