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
            'category'           => ['nullable', 'string', 'max:255'],
            'unit'               => ['nullable', 'string', 'max:255'],
            'quantity'           => ['nullable', 'numeric', 'gte:0', 'decimal:0,2'],
            'available_quantity' => ['nullable', 'numeric', 'gte:0', 'decimal:0,2'],
            'supplier'           => ['nullable', 'string', 'max:255'],
            'status'    => ['required', Rule::in(MaterialStatusEnum::values())],
            'threshold' => ['required', 'numeric', 'gte:0', 'decimal:0,2'],
            // Optional — when present, the controller also syncs qty + threshold
            // on the matching laboratory_materials row so the listing reflects the edit.
            'laboratory_material_id' => ['nullable', 'integer', 'exists:laboratory_materials,id'],
            // Optional — written to the latest laboratory_material_logs row for the
            // given laboratory_material_id so the displayed earliest expiry updates.
            'date_of_expiry' => ['nullable', 'date'],
        ];
    }

    public function messages()
    {
        return [
            'unit.regex' => 'The :attribute must be a string',
        ];
    }
}
