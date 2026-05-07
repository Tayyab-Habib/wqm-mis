<?php

namespace App\Http\Requests\WaterSampleDetail;

use Illuminate\Foundation\Http\FormRequest;

class StoreWaterSampleDetailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('add_water_sample_details');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'water_sample_id' => ['required', 'exists:water_samples,id', 'integer'],
            'test_id' => ['required', 'exists:tests,id', 'integer'],
        ];
    }
}
