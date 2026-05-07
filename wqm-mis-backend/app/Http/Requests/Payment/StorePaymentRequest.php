<?php

namespace App\Http\Requests\Payment;

use App\Enums\IssueTypeEnum;
use App\Enums\PaymentableTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('add_payments');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'is_pvt_client' => ['required', 'boolean'],
            'client_id' => ['nullable', 'integer', Rule::requiredIf($this->is_pvt_client === true), Rule::exists('clients', 'id')],
            'description' => ['required', 'string', 'max:65535'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date'],
            'details.*.paymentable_id' => ['required', 'numeric', Rule::exists('water_sample_invoice_logs', 'id')],
            'details.*.amount' => ['required', 'decimal:2'],
        ];
    }

    public function messages()
    {
        return [
            'details.*.amount.decimal' => 'The :attribute must be a valid decimal number',
        ];
    }
}
