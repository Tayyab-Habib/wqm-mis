<?php

namespace App\Http\Requests\Asset;

use App\Enums\AssetDisposalTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryOutRequest extends FormRequest
{
    public function authorize()
    {
        // Permission gate mirrors the asset log creation gate. Split into a
        // dedicated permission later if needed.
        return auth()->user()->can('add_asset_logs');
    }

    public function rules()
    {
        return [
            'asset_id'           => ['required', Rule::exists('assets', 'id')],
            'quantity'           => ['required', 'integer', 'min:1'],
            'unit'               => ['required', 'string', 'max:255'],
            'date'               => ['nullable', 'date_format:Y-m-d'],
            'type'               => ['required', Rule::in(AssetDisposalTypeEnum::values())],
            'recipient_name'     => ['nullable', 'string', 'max:255'],
            'recipient_role'     => ['nullable', 'string', 'max:255'],
            'asset_ref'          => ['nullable', 'string', 'max:255'],
            'remarks'            => ['nullable', 'string'],
            'recipient_lab_id'   => [
                'nullable',
                'required_if:type,' . AssetDisposalTypeEnum::TRANSFERRED->value,
                Rule::exists('laboratories', 'id'),
            ],
            'dispatch_reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages()
    {
        return [
            'quantity.integer' => 'The quantity must be a whole number (no decimals).',
            'quantity.min'     => 'The quantity must be at least 1.',
        ];
    }
}
