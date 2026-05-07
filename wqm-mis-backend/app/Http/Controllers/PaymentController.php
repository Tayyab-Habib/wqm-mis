<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payment\DeletePaymentRequest;
use App\Http\Requests\Payment\ShowPaymentRequest;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Requests\Payment\UpdatePaymentRequest;
use App\Http\Requests\Payment\ViewPaymentRequest;
use App\Models\Payment;
use App\Models\PaymentDetail;
use Pdf;
use App\Models\WaterSamples\WaterSampleInvoiceLog;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(ViewPaymentRequest $request)
    {
        $authUser = auth()->user();
        $query = Payment::query()
            ->with('createdByUser:id,name');

        if (!$authUser->hasRole('system-administrator')) {
            $query->where('laboratory_id', '=', $authUser->laboratoryUser->id);
        }

        $payments = $query->paginate(20);
        if (0 === $payments->total()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null,
            ], SymfonyResponse::HTTP_OK);
        }
        return response()->json([
            'message' => 'Success fetching payments',
            'data' => $payments
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StorePaymentRequest $request
     * @return JsonResponse
     */
    public function store(StorePaymentRequest $request)
    {
        try {
            DB::beginTransaction();
            $validatedData = $request->validated();

            $authUser = auth()->user();
            $laboratoryId = $authUser->laboratoryUser?->id;

            if (!$laboratoryId) {
                return response()->json([
                    'message' => 'Error creating payment, add laboratory to user first',
                    'data' => null,
                ], SymfonyResponse::HTTP_FORBIDDEN);
            }
            $total = collect($validatedData['details'])->sum('amount');

            $payment = Payment::query()->create([
                'total' => $total,
                'laboratory_id' => $laboratoryId,
                'description' => $validatedData['description']
            ]);

            foreach ($validatedData['details'] as $index => $_) {

                $paymentDetailData[] = [
                    'payment_id' => $payment->id,
                    'paymentable_type' => WaterSampleInvoiceLog::class,
                    'paymentable_id' => $validatedData['details'][$index]['paymentable_id'],
                    'amount' => $validatedData['details'][$index]['amount'] * 100,
                    'created_at' => now(),
                ];
            }

            PaymentDetail::query()
                ->insert($paymentDetailData);
            DB::commit();
        } catch (\Exception $exception) {
            info($exception->getMessage());

            return response()->json([
                'message' => 'Error creating payment',
                'data' => null,
            ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        return response()->json([
            'message' => 'Success creating payment',
            'data' => $payment->load('paymentDetails'),
        ], SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowPaymentRequest $request
     * @param Payment $payment
     * @return JsonResponse
     */
    public function show(ShowPaymentRequest $request, Payment $payment)
    {
        if ($this->restrictRelatedPayment($payment)) {
            return response()->json([
                'message' => 'Error user unauthorized',
                'data' => null,
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }

        $payment = $this->getPaymentDetail($payment);
        return response()->json([
            'message' => 'Success fetching payment',
            'data' => $payment
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdatePaymentRequest $request
     * @param Payment $payment
     * @return JsonResponse
     */
    public function update(UpdatePaymentRequest $request, Payment $payment)
    {
        if ($this->restrictRelatedPayment($payment)) {
            return response()->json([
                'message' => 'Error user unauthorized',
                'data' => null,
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }

        $payment->update($request->validated());

        if ($payment->wasChanged()) {
            return response()->json([
                'message' => 'Success updating payment',
                'data' => $payment
            ]);
        }
        return response()->json([
            'message' => 'Error updating payment'
        ], SymfonyResponse::HTTP_BAD_REQUEST);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DeletePaymentRequest $request
     * @param Payment $payment
     * @return JsonResponse
     */
    public function destroy(DeletePaymentRequest $request, Payment $payment)
    {
        try {
            DB::beginTransaction();

            if ($this->restrictRelatedPayment($payment)) {
                return response()->json([
                    'message' => 'Error user unauthorized',
                    'data' => null,
                ], SymfonyResponse::HTTP_FORBIDDEN);
            }

            $payment->paymentDetails()->delete();

            $payment->delete();

            DB::commit();
        } catch (\Exception $exception) {
            info($exception->getMessage());

            return response()->json([
                'message' => 'Error deleting payment',
                'data' => null,
            ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        return response()->json([
            'message' => 'Success deleting payment',
            'data' => null
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Checked whether payment is related to concerned person
     *
     * @param Payment $payment
     * @return bool
     */
    protected function restrictRelatedPayment(Payment $payment): bool
    {
        $authUser = auth()->user();

        if ($authUser->hasRole('system-administrator')) {
            return false;
        }

        if ((int)$payment->created_by === $authUser->id) {
            return false;
        }

        return true;
    }

    public function generatePdf(Payment $payment)
    {
        if ($this->restrictRelatedPayment($payment)) {
            return response()->json([
                'message' => 'Error user unauthorized',
                'data' => null,
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }

        $payment = $this->getPaymentDetail($payment);

        $pdf = \PDF::loadView('waterSample.payment', compact('payment'));

        $pdf->setOption('page-size', 'A4');

        $fileName = 'water-samples-payment-' . Str::replace('/', '-', $payment?->slug) . '-' . now()->format('YmdTHis') . '.pdf';
        return $pdf->download($fileName);
    }

    public function getPaymentDetail(Payment $payment): Payment
    {

        return $payment->load([
            'createdByUser:id,name',
            'laboratory:id,name,logo,province_id,address,email,fax' => [
                'province:id,name,logo'
            ],
            'modifiedByUser:id,name',
            'paymentDetails' => [
                'paymentable' => function (MorphTo $morphTo) {
                    $morphTo->constrain([
                        WaterSampleInvoiceLog::class => function ($query) {
                            $query->with([
                                'waterSampleInvoice:id,invoiceable_id,invoiceable_type,created_at,water_sample_id' => [
                                    'waterSample:id,slug',
                                    'invoiceable:id,name'
                                ],
                            ]);
                        }
                    ]);
                }
            ],
        ]);
    }
}
