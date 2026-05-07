<?php

namespace App\Http\Requests\Material;

use App\Enums\MaterialStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaterialRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('edit_material');
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
                $query->whereNull('deleted_at')
                    ->whereNot('id', '=', $this->material->id);
            }), 'max:255'],
            'status' => ['required', Rule::in(MaterialStatusEnum::values())],
            'threshold' => ['required', 'numeric', 'gte:0', 'decimal:2'],
        ];
    }

    public function messages()
    {
        return [
            'unit.regex' => 'The :attribute must be a string',
        ];
    }
}
