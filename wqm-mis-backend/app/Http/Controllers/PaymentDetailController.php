<?php

namespace App\Http\Controllers;

use App\Enums\PaymentableTypeEnum;
use App\Http\Requests\Payment\UpdatePaymentDetailRequest;
use App\Models\PaymentDetail;
use App\Models\WaterSamples\WaterSample;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PaymentDetailController extends Controller
{
    /**
     * Update the specified resource in storage.
     *
     * @param \App\Http\Requests\Payment\UpdatePaymentDetailRequest $request
     * @param \App\Models\PaymentDetail $paymentDetail
     * @return JsonResponse
     */
    public function update(UpdatePaymentDetailRequest $request, PaymentDetail $paymentDetail)
    {
        if ($this->restrictRelatedPaymentDetail($paymentDetail)) {
            return response()->json([
                'message' => 'Error user unauthorized',
                'data' => null,
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }

        $validatedData = $request->validated();

        foreach ($validatedData['amount'] as $index => $_) {
            switch ($validatedData['paymentable_type'][$index]) {
                case PaymentableTypeEnum::WATER_SAMPLE->value:
                    $paymentableType = WaterSample::class;
                    break;
            }

            $paymentData = [
                'payment_id' => $validatedData['payment_id'],
                'paymentable_type' => $paymentableType,
                'paymentable_id' => $validatedData['paymentable_id'][$index],
                'amount' => $validatedData['amount'][$index],
            ];

            $paymentDetail->update($paymentData);

            $total = array_sum($validatedData['amount']);

            $paymentDetail->payment()->update(['total' => $total]);

            if ($paymentDetail->wasChanged()) {
                return response()->json([
                    'message' => 'Success updating payment detail',
                    'data' => $paymentDetail->load('payment')
                ]);
            }
        }

        return response()->json([
            'message' => 'Error updating payment detail'
        ], SymfonyResponse::HTTP_BAD_REQUEST);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\PaymentDetail $paymentDetail
     * @return JsonResponse
     */
    public function destroy(PaymentDetail $paymentDetail)
    {
        if ($this->restrictRelatedPaymentDetail($paymentDetail)) {
            return response()->json([
                'message' => 'Error user unauthorized',
                'data' => null,
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }

        $paymentDetail->delete();

        return response()->json([
            'message' => 'Success deleting payment detail',
            'data' => null
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Checked whether payment is related to concerned person
     *
     * @param PaymentDetail $paymentDetail
     * @return bool
     */
    protected function restrictRelatedPaymentDetail(PaymentDetail $paymentDetail): bool
    {
        $authUser = auth()->user();

        if ($authUser->isUnscoped()) {
            return false;
        }

        if ((int)$paymentDetail->payment->user_id === $authUser->id) {
            return false;
        }

        return true;
    }
}
