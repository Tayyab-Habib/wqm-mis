<?php

namespace App\Http\Resources;

use App\Enums\PaymentableTypeEnum;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */

    public function toArray($request)
    {
        $paymentableType = PaymentableTypeEnum::WATER_SAMPLE->value;

        return [
            'id' => $this->id,
            'paymentable_type' => $paymentableType,
            'paymentable_id' => $this->paymentable_id,
            'amount' => $this->amount,
            'paymentable' => $this->paymentable,
        ];
    }
}
