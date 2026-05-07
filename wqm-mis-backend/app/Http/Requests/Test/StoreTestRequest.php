<?php

namespace App\Http\Requests\Test;

use App\Enums\TestTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('add_tests');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'type' => ['required', 'string', Rule::in(TestTypeEnum::values())],
            'water_quality_parameter' => ['required', 'unique:tests,water_quality_parameter', 'max:255'],
            'unit' => ['required', 'max:255'],
            'detectable_limit' => ['required', 'numeric'],
            'reference_method' => ['required', 'max:255'],
            'who_guideline_start' => ['required', 'decimal:2', 'lt:who_guideline_end'],
            'who_guideline_end' => ['required', 'decimal:2'],
            'laboratory_guideline_start' => ['required', 'decimal:2', 'lt:laboratory_guideline_end'],
            'laboratory_guideline_end' => ['required', 'decimal:2'],
            'rate' => ['required', 'numeric'],
            'criteria' => ['required', 'bool'],
        ];
    }


    /**
     * Get the validation error messages.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'who_guideline_start.decimal' => 'The :attribute must have 2 decimal places.',
            'who_guideline_end.decimal' => 'The :attribute must have 2 decimal places.',
            'laboratory_guideline_start.decimal' => 'The :attribute must have 2 decimal places.',
            'laboratory_guideline_end.decimal' => 'The :attribute must have 2 decimal places.',
        ];
    }
}
