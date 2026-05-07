<?php

namespace App\Http\Requests\Asset;

use App\Enums\AssetLogStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssetLogRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('edit_asset_logs');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'asset_id' => ['required', Rule::exists('assets', 'id')],
            'quantity' => ['required', 'decimal:2'],
            'unit' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(AssetLogStatusEnum::values())]
        ];
    }

    public function messages()
    {
        return [
            'quantity.decimal' => 'The :attribute must be a valid decimal number',
        ];
    }
}
