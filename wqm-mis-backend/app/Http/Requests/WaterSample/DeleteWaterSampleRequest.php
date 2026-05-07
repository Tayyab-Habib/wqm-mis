<?php

namespace App\Http\Requests\WaterSample;

use App\Enums\CollectableTypeEnum;
use App\Enums\CollectedByEnum;
use App\Enums\CollectedInEnum;
use App\Enums\DesiredTestEnum;
use App\Enums\ReasonForTestingEnum;
use App\Enums\SamplingPointEnum;
use App\Enums\SourceTypeEnum;
use App\Enums\WaterSampleStatusEnum;
use App\Enums\TestFrequencyEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteWaterSampleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('delete_water_samples');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            //
        ];
    }
}
