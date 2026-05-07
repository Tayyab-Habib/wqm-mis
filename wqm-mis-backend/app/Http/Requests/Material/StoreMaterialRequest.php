<?php

namespace App\Http\Requests\Material;

use App\Enums\MaterialStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaterialRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('add_material');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => ['required', Rule::unique('materials')->where(function ($query) {
                $query->whereNull('deleted_at');
            }), 'max:255'],
            'quantity' => ['required', 'decimal:2', 'gte:0'],
            'unit' => ['required', 'string', 'max:255', 'regex:/[a-zA-Z\.\/\s]+/'],
            'threshold' => ['required', 'decimal:2', 'gte:0'],
            'date_of_expiry' => ['required', 'date_format:Y-m-d', 'after_or_equal:' . now()->format('Y-m-d')],
            'status' => ['required', Rule::in(MaterialStatusEnum::values())],
        ];
    }

    public function messages()
    {
        return [
            'unit.regex' => 'The :attribute must be a string',
            'quantity.decimal' => 'The quantity must be a valid decimal number',
            'threshold.decimal' => 'The :attribute must be a valid decimal number',
        ];
    }
}
