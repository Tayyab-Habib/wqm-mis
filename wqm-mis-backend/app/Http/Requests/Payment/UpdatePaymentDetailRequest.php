<?php

namespace App\Http\Requests\Payment;

use App\Enums\PaymentableTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentDetailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $u = auth()->user();
        return $u && ($u->isUnscoped() || $u->can('edit_payments'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'payment_id' => ['required', 'numeric', Rule::exists('payments', 'id')],
            'details.*.paymentable_type' => ['required', Rule::in(PaymentableTypeEnum::values())],
            'details.*.paymentable_id' => ['required', 'numeric', Rule::exists('water_samples', 'id')],
            'details.*.amount' => ['required', 'integer'],
        ];
    }
}
