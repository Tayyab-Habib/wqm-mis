<?php

namespace App\Http\Controllers\Dropdowns;

use App\Enums\InvoiceableTypeEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class InvoiceableTypeController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Success fetching invoiceable types',
            'data' => InvoiceableTypeEnum::array(),
        ], SymfonyResponse::HTTP_OK);
    }
}
