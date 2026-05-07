<?php

namespace App\Http\Requests\Asset;

use App\Enums\AssetLogStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetLogRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('add_asset_logs');
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
            'quantity' => ['required', 'numeric', 'gte:0'],
            'date_of_expiry' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:' . now()->format('Y-m-d')],
            'unit' => ['required', 'string', 'max:255'],
        ];
    }
}
