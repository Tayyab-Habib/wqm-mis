<?php

namespace App\Http\Requests\Material;

use App\Enums\MaterialLogStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaterialLogRequest extends FormRequest
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
            'material_id' => ['required', Rule::exists('materials', 'id')],
            'date_of_expiry' => ['required', 'date', 'after:' . now()],
            'quantity' => ['required', 'decimal:2'],
            'unit' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(MaterialLogStatusEnum::values())],
        ];
    }

    public function messages()
    {
        return [
            'quantity.decimal' => 'The quantity must be a valid decimal number'
        ];
    }
}
