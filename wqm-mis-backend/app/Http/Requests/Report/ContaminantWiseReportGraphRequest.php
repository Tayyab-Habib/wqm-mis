<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContaminantWiseReportGraphRequest extends FormRequest
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
            'date_from' => ['nullable', 'date', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'required_with:date_from', 'date', 'after_or_equal:date_from', 'date_format:Y-m-d'],
            'test_id' => ['nullable', Rule::exists('tests', 'id')],
            'district_id' => ['nullable', Rule::exists('districts', 'id')],
        ];
    }
}
