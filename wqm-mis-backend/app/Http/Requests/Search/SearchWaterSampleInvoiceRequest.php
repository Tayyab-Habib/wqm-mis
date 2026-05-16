<?php

namespace App\Http\Requests\Search;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchWaterSampleInvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return !!auth()?->id();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            // is_pvt_client used to be required, which 422'd the Invoices list
            // when the frontend loads with no filters yet. Now nullable so an
            // empty payload returns the unfiltered list.
            'is_pvt_client' => ['nullable', 'boolean'],
            'client_id' => ['nullable', 'integer', Rule::requiredIf($this->is_pvt_client === true), Rule::exists('clients', 'id')],
            'date_from' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:' . now()->subMonth(3)->format('Y-m-d')],
            'date_to' => ['nullable', 'date','date_format:Y-m-d', 'before_or_equal:' . now()->format('Y-m-d')],
        ];
    }
}
