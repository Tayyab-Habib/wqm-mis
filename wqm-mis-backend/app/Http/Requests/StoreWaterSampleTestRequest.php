<?php

namespace App\Http\Requests;

use App\Rules\NumberOrStringRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreWaterSampleTestRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->user()->can('edit_water_sample_results');
    }

    public function rules()
    {
        return [
            'analysis_results' => ['required', 'array'],
            'analysis_results.*.test_id' => ['required', 'exists:tests,id'],
            'analysis_results.*.analysis_result' => ['required', 'max:255', new NumberOrStringRule()],
            'is_draft' => ['required', 'boolean'],
            'remarks' => ['required', 'string', 'max:65535'],
            
            // Retest Fields
            'source_type' => ['nullable', 'string', 'max:255'],
            'complaint' => ['nullable', 'string', 'max:255'],
            'desired_test' => ['nullable', 'array'],
            'sample_status' => ['nullable', 'string', 'max:255'],
            'on_demand_test' => ['nullable', 'boolean'],
            
            // Step 3 fields for Retest
            'sampling_point' => ['nullable', 'string', 'max:255'],
            'collected_by' => ['nullable', 'string', 'max:255'],
            'collected_in' => ['nullable', 'string', 'max:255'],
            'collected_in_other' => ['nullable', 'string', 'max:255'],
            'temperature_in_celsius' => ['nullable', 'numeric'],
            'sampled_at' => ['nullable', 'date'],
            'reported_at' => ['nullable', 'date'],
        ];
    }
}
