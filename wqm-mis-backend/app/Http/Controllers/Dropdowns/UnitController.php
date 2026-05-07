<?php

namespace App\Http\Controllers\Dropdowns;

use App\Enums\InvoiceableTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class UnitController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param InvoiceableTypeEnum $invoiceableTypeEnum
     * @return JsonResponse
     */
    public function __invoke(Request $request, InvoiceableTypeEnum $invoiceableTypeEnum)
    {
        return response()->json([
            'message' => 'Success fetching unit types',
            'data' => Unit::query()
                ->where('type', '=', $invoiceableTypeEnum->value)
                ->get(),
        ], SymfonyResponse::HTTP_OK);
    }
}
