<?php

namespace App\Http\Requests\Export;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportUserRequest extends FormRequest
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
            'laboratory_id' => ['nullable', Rule::exists('laboratories', 'id')],
            'district_id' => ['nullable', Rule::exists('districts', 'id')],
            'division_id' => ['nullable', Rule::exists('divisions', 'id')],
            'district_id' => ['nullable', Rule::exists('districts', 'id')],
            'designation_id' => ['nullable', Rule::exists('designations', 'id')],
            'is_active' => ['nullable', 'boolean'],

        ];
    }
}
