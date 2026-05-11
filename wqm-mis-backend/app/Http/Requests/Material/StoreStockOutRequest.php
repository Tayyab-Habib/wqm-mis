<?php

namespace App\Http\Requests\Material;

use App\Enums\StockOutTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockOutRequest extends FormRequest
{
    public function authorize()
    {
        // Same permission gate used by MaterialLogController. If we need a
        // separate `add_stock_out` permission later we can split this.
        return auth()->user()->can('add_material_logs');
    }

    public function rules()
    {
        return [
            'material_id'     => ['required', Rule::exists('materials', 'id')],
            'quantity'        => ['required', 'decimal:2', 'gt:0'],
            'unit'            => ['required', 'string', 'max:255'],
            'date'            => ['nullable', 'date_format:Y-m-d'],
            'type'            => ['required', Rule::in(StockOutTypeEnum::values())],
            'recipient_name'  => ['nullable', 'string', 'max:255'],
            'recipient_role'  => ['nullable', 'string', 'max:255'],
            'sample_ref'      => ['nullable', 'string', 'max:255'],
            'remarks'         => ['nullable', 'string'],
            // Inter-lab issuance fields (only populated when type=inter_lab_issuance)
            'recipient_lab_id' => [
                'nullable',
                'required_if:type,' . StockOutTypeEnum::TRANSFER->value . ',' . StockOutTypeEnum::INTER_LAB_ISSUANCE->value,
                Rule::exists('laboratories', 'id'),
            ],
            'demand_id'        => ['nullable', Rule::exists('inventories', 'id')],
            'dispatch_reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages()
    {
        return [
            'quantity.decimal' => 'The quantity must be a valid decimal number',
            'quantity.gt'      => 'The quantity must be greater than zero',
        ];
    }
}
