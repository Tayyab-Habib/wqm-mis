<?php

namespace App\Http\Requests\SopWaterSample;

use App\Enums\SopWaterSampleEnum;
use App\Models\SopWaterSample;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSopWaterSampleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('add_water_sample_sop');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'type' => ['required', Rule::in(SopWaterSampleEnum::values())],
            'description' => ['required'],
        ];
    }
}
