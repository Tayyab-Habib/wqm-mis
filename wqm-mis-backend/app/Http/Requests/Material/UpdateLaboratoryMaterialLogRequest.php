<?php

namespace App\Http\Requests\Material;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLaboratoryMaterialLogRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $u = auth()->user();
        return $u && ($u->isUnscoped() || $u->can('edit_material'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'threshold' => ['required', 'gt:0', 'numeric'],
        ];
    }
}
