<?php

namespace App\Http\Requests\Search;

use App\Enums\SourceTypeEnum;
use App\Enums\TestFrequencyEnum;
use App\Enums\WaterSampleStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchWaterSampleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return !!auth()?->id();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'test_type' => ['nullable', Rule::enum(TestFrequencyEnum::class)],
            'water_scheme_id' => ['nullable', 'integer', 'exists:water_schemes,id'],
            'source_type' => ['nullable', Rule::enum(SourceTypeEnum::class)],
            'status' => ['nullable', Rule::enum(WaterSampleStatusEnum::class)],
            'union_council_id' => ['nullable', 'integer', 'exists:union_councils,id'],
            'tehsil_id' => ['nullable', 'integer', 'exists:tehsils,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
        ];
    }
}
