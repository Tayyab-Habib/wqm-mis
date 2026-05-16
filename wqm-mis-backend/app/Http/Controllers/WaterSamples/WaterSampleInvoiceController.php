<?php

namespace App\Http\Controllers\WaterSamples;

use App\Enums\TestTypeEnum;
use App\Enums\WaterSampleInvoiceStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\WaterSampleInvoice\IndexWaterSampleInvoiceRequest;
use App\Http\Requests\WaterSampleInvoice\ShowWaterSampleInvoiceRequest;
use App\Http\Requests\WaterSampleInvoice\UpdateWaterSampleInvoiceRequest;
use App\Models\Test;
use Illuminate\Support\Str;
use PDF;
use App\Models\WaterSamples\WaterSampleInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class WaterSampleInvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param IndexWaterSampleInvoiceRequest $request
     * @return JsonResponse
     */
    public function index(IndexWaterSampleInvoiceRequest $request): JsonResponse
    {
        $authUser = auth()->user();
        $waterSampleInvoices = WaterSampleInvoice::query()
            ->when(!$authUser->isUnscoped(), fn($query) => $query->where('created_by', '=', $authUser->id))
            ->has('waterSample')
            ->with([
                'waterSample:id,slug' => [
                    'waterScheme:id,name',
                ],
                'client:id,name',
                'createdByUser:id,name'
            ])
            ->paginate(20);

        if (0 === $waterSampleInvoices->total()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null,
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success fetching water samples',
            'data' => $waterSampleInvoices
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowWaterSampleInvoiceRequest $request
     * @param WaterSampleInvoice $waterSampleInvoice
     * @return JsonResponse
     */
    public function show(ShowWaterSampleInvoiceRequest $request, WaterSampleInvoice $waterSampleInvoice)
    {
        $waterSampleInvoice->load([
            'waterSample' => [
                'collectable:id,name,phone,email',
                'laboratory:id,name,address,email,fax,phone,logo',
                'province:id,logo',
                'district:id,name',
                'waterScheme:id,name'
            ],
            'waterSampleInvoiceLogs.user:id,name'
        ]);

        $desiredTests = Test::query()
            ->select('water_quality_parameter')
            ->whereHas('waterSampleDetails', fn($query) => $query->where('water_sample_id', '=', $waterSampleInvoice->water_sample_id)
                ->where('type', '=', TestTypeEnum::ON_DEMAND->value))
            ->pluck('water_quality_parameter')->toArray();

        $desiredTests = implode(', ', $desiredTests);

        return response()->json([
            'message' => 'Success fetching water sample invoice',
            'data' => [
                'water_sample_invoice' => $waterSampleInvoice,
                'desired_test' => $desiredTests,
            ],
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * D-09 — fixed partial-payment math.
     *
     * Previously:
     *   1. `$validatedData['paid'] + $waterSampleInvoice->net_amount ?? 0`
     *      had wrong operator precedence (`??` lowest priority), so the
     *      `?? 0` applied to the whole subtraction, never the net_amount.
     *   2. `'paid' => $validatedData['paid']` OVERWROTE the cumulative
     *      paid total instead of advancing it — partial payments dropped
     *      history.
     *
     * Now:
     *   • `paid` accumulates: new_paid = current_paid + amount_received.
     *   • Inputs over the outstanding balance are rejected.
     *   • Status is derived from the new balance, not the delta.
     *   • Log entry carries both delta (`paid`) and running balance.
     *
     * NOTE: New SPA flows use FinanceInvoiceController::recordPayment, which
     * also validates payment_mode / receipt_no per F-03. This legacy method
     * is kept for backwards-compat with `PUT /api/water-sample-invoices/{id}`
     * but is functionally aligned.
     */
    public function update(UpdateWaterSampleInvoiceRequest $request, WaterSampleInvoice $waterSampleInvoice)
    {
        $validatedData = $request->validated();

        $amount  = round((float) $validatedData['paid'], 2);
        $newPaid = round((float) $waterSampleInvoice->paid + $amount, 2);
        $newBal  = round(max(0, (float) $waterSampleInvoice->net_amount - $newPaid), 2);

        if ($amount <= 0) {
            return response()->json([
                'message' => 'Invalid paid amount',
                'errors'  => ['paid' => ['Amount must be greater than zero.']],
            ], SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($newPaid > (float) $waterSampleInvoice->net_amount + 0.001) {
            return response()->json([
                'message' => 'Paid amount exceeds remaining balance',
                'errors'  => ['paid' => ['Payment would exceed outstanding balance.']],
            ], SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $status = $newBal <= 0
            ? WaterSampleInvoiceStatusEnum::PAID->value
            : WaterSampleInvoiceStatusEnum::PARTIAL->value;

        DB::beginTransaction();
        try {
            $waterSampleInvoice->waterSampleInvoiceLogs()->create([
                'user_id'          => auth()->id(),
                'paid'             => $amount,
                'balance'          => $newBal,
                'payment_mode'     => $validatedData['payment_mode']     ?? null,
                'receipt_no'       => $validatedData['receipt_no']       ?? null,
                'payment_date'     => $validatedData['payment_date']     ?? now()->toDateString(),
                'received_by_name' => $validatedData['received_by']      ?? auth()->user()?->name,
                'note'             => $validatedData['remarks']          ?? null,
            ]);

            $waterSampleInvoice->update([
                'paid'    => $newPaid,
                'balance' => $newBal,
                'status'  => $status,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error updating water sample invoice'], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'message' => 'Success updating water sample invoice',
            'data'    => $waterSampleInvoice->fresh(),
        ], SymfonyResponse::HTTP_OK);
    }

    public function generatePdf(WaterSampleInvoice $waterSampleInvoice)
    {
        $waterSampleInvoice->load([
            'waterSample' => [
                'collectable:id,name,phone,email',
                'laboratory:id,name,address,email,fax,phone,logo',
                'province:id,logo',
                'district:id,name',
                'waterScheme:id,name',
            ],
            'waterSampleInvoiceLogs.user:id,name'
        ]);

        $desiredTests = Test::query()
            ->select('water_quality_parameter')
            ->whereHas('waterSampleDetails', fn($query) => $query->where('water_sample_id', '=', $waterSampleInvoice->water_sample_id)
                ->where('type', '=', TestTypeEnum::ON_DEMAND->value))
            ->pluck('water_quality_parameter')->toArray();

        $desiredTests = implode(', ', $desiredTests);

        $pdf = PDF::loadView('waterSample.invoice', compact('waterSampleInvoice', 'desiredTests'));

        $pdf->setOption('page-size', 'A4');

        $fileName = 'water-sample-invoice-' . Str::replace('/', '-', $waterSampleInvoice?->waterSample?->slug) . '-' . now()->format('YmdTHis') . '.pdf';
        return $pdf->download($fileName);
    }
}
