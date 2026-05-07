<?php

namespace App\Http\Requests\Invoice;

use App\Enums\InvoiceableTypeEnum;
use App\Rules\MorphedRelationArrayRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('add_invoices');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'description' => ['required'],
            'amount' => ['required', 'integer'],
            'file' => ['nullable', 'image', 'mimes:jpeg,jpg,png', 'mimetypes:image/jpeg,image/png'],
            'details.*.invoiceable_type' => ['required', Rule::enum(InvoiceableTypeEnum::class)],
            'details.*.invoiceable_id' => [
                'required',
                'numeric',
                new MorphedRelationArrayRule()
            ],
            'details.*.name' => ['required', 'string', 'max:255'],
            'details.*.quantity' => ['required', 'decimal:2'],
            'details.*.unit' => ['required', 'string', 'max:255'],
            'details.*.price' => ['required', 'decimal:2']
        ];
    }

    public function messages()
    {
        return [
            'details.*.quantity.decimal' => 'The :attribute must be a valid decimal number',
            'details.*.price.decimal' => 'The :attribute must be a valid decimal number',
        ];
    }
}
