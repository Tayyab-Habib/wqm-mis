<?php

namespace App\Http\Controllers\Dropdowns;

use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PurchaseOrderStatusController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Success fetching purchase order statuses',
            'data' => PurchaseOrderStatus::array(),
        ], SymfonyResponse::HTTP_OK);
    }
}
