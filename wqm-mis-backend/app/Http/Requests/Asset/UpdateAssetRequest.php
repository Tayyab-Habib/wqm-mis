<?php

namespace App\Http\Requests\Asset;

use App\Enums\AssetStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('edit_assets');
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
                $query->whereNull('deleted_at')
                    ->whereNot('id', '=', $this->asset->id);
            }), 'max:255'],
            'kind'             => ['nullable', Rule::in(['inventory', 'equipment'])],
            'category'         => ['nullable', 'string', 'max:64'],
            'item_code'        => ['nullable', 'string', 'max:64', Rule::unique('assets', 'item_code')
                ->ignore($this->asset?->id)->whereNull('deleted_at')],
            'date_of_expiry'   => ['nullable', 'date_format:Y-m-d'],
            'status'           => ['required', Rule::in(AssetStatusEnum::values())],
            'condition'        => ['nullable', Rule::in(['good', 'fair', 'poor', 'condemned'])],
            'date_of_purchase' => ['nullable', 'date_format:Y-m-d'],
            'purchase_value'   => ['nullable', 'numeric', 'gte:0'],
            'location'         => ['nullable', 'string', 'max:255'],
            'last_verified'    => ['nullable', 'date_format:Y-m-d'],
            'remarks'          => ['nullable', 'string'],
            'specification'    => ['nullable', 'string', 'max:65535'],
            'country'          => ['nullable', 'string', 'max:255'],
            'agency'           => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages()
    {
        return [
            'unit.regex' => 'The :attribute must be a string',
            'threshold.decimal' => 'The :attribute must be a valid decimal number',
        ];
    }

}
