<?php

namespace App\Http\Requests\Material;

use App\Enums\MaterialLogStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaterialLogRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('add_material_logs');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'material_id' => ['required', Rule::exists('materials', 'id')],
            'date_of_expiry' => ['required', 'date', 'after_or_equal:' . now()->format('Y-m-d')],
            'quantity' => ['required', 'decimal:2'],
            'unit' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages()
    {
        return [
            'quantity.decimal' => 'The quantity must be a valid decimal number'
        ];
    }
}
