<?php

namespace App\Http\Requests\Export;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportLaboratoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'division_id' => ['nullable', Rule::exists('divisions', 'id')],
            'district_id' => ['nullable', Rule::exists('districts', 'id')],
            'tehsil_id' => ['nullable', Rule::exists('tehsils', 'id')],
            'is_active' => ['nullable', 'boolean'],

        ];
    }
}
