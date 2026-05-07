<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceableTypeEnum;
use App\Enums\IssueTypeEnum;
use App\Http\Requests\Invoice\DeleteInvoiceRequest;
use App\Http\Requests\Invoice\ShowInvoiceRequest;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\ViewInvoiceRequest;
use App\Models\Asset\Asset;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Material\Material;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(ViewInvoiceRequest $request)
    {
        $invoices = Invoice::query()
            ->with('createdByUser:id,name',)
            ->get();

        if ($invoices->isEmpty()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null,
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success fetching invoices',
            'data' => $invoices
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreInvoiceRequest $request
     * @return JsonResponse
     */
    public function store(StoreInvoiceRequest $request)
    {
        try {
            $validatedData = $request->validated();

            DB::beginTransaction();

            if ($request->has('file')) {
                $path = Storage::disk('public')->put('/invoices', $request->file);
                $validatedData = array_merge($validatedData, ['file' => $path]);
            }

            $invoice = Invoice::query()
                ->create($validatedData);

            foreach ($validatedData['details'] as $detail) {
                $invoiceableType = match ($detail['invoiceable_type']) {
                    InvoiceableTypeEnum::STOCK->value => Material::class,
                    InvoiceableTypeEnum::INVENTORY->value => Asset::class,
                };

                $invoiceData = [
                    'invoiceable_type' => $invoiceableType,
                    'invoiceable_id' => $detail['invoiceable_id'],
                    'invoice_id' => $invoice->id,
                    'name' => $detail['name'],
                    'quantity' => $detail['quantity'],
                    'unit' => $detail['unit'],
                    'price' => $detail['price'],
                ];


                InvoiceDetail::query()
                    ->create($invoiceData);
            }
            DB::commit();
        } catch (\Exception $exception) {
            info($exception->getMessage());

            return response()->json([
                'message' => 'Error creating invoice',
                'data' => null,
            ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        return response()->json([
            'message' => 'Success creating invoice',
            'data' => $invoice,
        ], SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     *
     * @param Invoice $invoice
     * @return JsonResponse
     */
    public function show(ShowInvoiceRequest $request, Invoice $invoice)
    {
        $invoice->load('invoiceDetails.invoiceable:id,name', 'createdByUser:id,name','modifiedByUser:id,name');

        foreach ($invoice->invoiceDetails as $invoiceDetail) {
            $invoiceDetail->invoiceable_type = $invoiceDetail->invoiceable_type === Asset::class
                ? IssueTypeEnum::INVENTORY->value
                : IssueTypeEnum::STOCK->value;
        }

        return response()->json([
            'message' => 'Success fetching invoice',
            'data' => $invoice
        ], SymfonyResponse::HTTP_OK);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param Invoice $invoice
     * @return JsonResponse
     */
    public function destroy(DeleteInvoiceRequest $request, Invoice $invoice)
    {
        try {
            DB::beginTransaction();

            InvoiceDetail::query()
                ->where('invoice_id', '=', $invoice->id)
                ->delete();

            $invoice->delete();
            DB::commit();

            return response()->json([
                'message' => 'Success deleting invoice',
                'data' => $invoice,
            ], SymfonyResponse::HTTP_OK);
        } catch (\Exception $exception) {
            info($exception->getMessage());

            return response()->json([
                'message' => 'Error deleting invoice',
                'data' => null,
            ], SymfonyResponse::HTTP_NOT_FOUND);
        }
    }
}
