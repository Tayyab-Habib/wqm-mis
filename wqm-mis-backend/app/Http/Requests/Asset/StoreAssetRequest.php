<?php

namespace App\Http\Requests\Asset;

use App\Enums\AssetStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('add_assets');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => ['required', Rule::unique('assets')->where(function ($query) {
                $query->whereNull('deleted_at');
            }), 'max:255'],
            'kind' => ['nullable', Rule::in(['inventory', 'equipment'])],
            'category' => ['nullable', 'string', 'max:64'],
            'item_code' => ['nullable', 'string', 'max:64', Rule::unique('assets', 'item_code')->whereNull('deleted_at')],
            'quantity' => ['required', 'decimal:2', 'gte:0'],
            'unit' => ['required', 'string', 'max:255', 'regex:/[a-zA-Z\.\/\s]+/'],
            'date_of_expiry' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:' . now()->format('Y-m-d')],
            'status' => ['required', Rule::in(AssetStatusEnum::values())],
            'condition' => ['nullable', Rule::in(['good', 'fair', 'poor', 'condemned'])],
            'date_of_purchase' => ['nullable', 'date_format:Y-m-d'],
            'purchase_value' => ['nullable', 'numeric', 'gte:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'last_verified' => ['nullable', 'date_format:Y-m-d'],
            'remarks' => ['nullable', 'string'],
            // The next 3 were originally required but SRS §2.7-2 doesn't list them
            // for Non-consumables. Keeping for backward compat but as nullable.
            'specification' => ['nullable', 'string', 'max:65535'],
            'country' => ['nullable', 'string', 'max:255'],
            'agency' => ['nullable', 'string', 'max:255'],
            // SRS §2.7-3 equipment-specific fields (stored on laboratory_assets,
            // not on the master assets row). AssetController::store forwards
            // these to LaboratoryAsset->create() during the 4-table workflow.
            'make_model'         => ['nullable', 'string', 'max:255'],
            'serial_number'      => ['nullable', 'string', 'max:255'],
            'purchased_at'       => ['nullable', 'date_format:Y-m-d'],
            'warranty_expiry'    => ['nullable', 'date_format:Y-m-d'],
            'calibration_cycle'  => ['nullable', 'string', 'max:64'],
        ];
    }

    public function messages()
    {
        return [
            'unit.regex' => 'The :attribute must be a string',
            'quantity.decimal' => 'The :attribute must be a valid decimal number',
        ];
    }
}
