<?php

namespace App\Http\Requests\WaterSampleDetail;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWaterSampleDetailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('edit_water_sample_details');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'test_id' => ['required', 'exists:tests,id', 'integer'],
        ];
    }
}
