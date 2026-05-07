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
            'date_of_expiry' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:' . now()->format('Y-m-d')],
            'status' => ['required', Rule::in(AssetStatusEnum::values())],
            'specification' => ['required', 'string', 'max:65535'],
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
