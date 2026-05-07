<?php

namespace App\Http\Requests\WaterSampleDetail;

use App\Enums\WaterSampleResultEnum;
use App\Rules\NumberOrStringRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWaterSampleResultRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('edit_water_sample_results');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'analysis_results' => ['required', 'array'],
            'analysis_results.*.test_id' => ['required', 'exists:tests,id'],
            'analysis_results.*.analysis_result' => ['required', 'max:255', new NumberOrStringRule()],
            'is_draft' => ['required', 'boolean'],
            'remarks' => ['required', 'string', 'max:65535'],
        ];
    }
}
