<?php

namespace App\Http\Requests\Search;

use App\Enums\CollectedByEnum;
use App\Enums\SamplingPointEnum;
use App\Enums\SourceTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchWaterSampleResultRequest extends FormRequest
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
            'water_scheme_id' => ['nullable', 'integer', 'exists:water_schemes,id'],
            'source_type' => ['nullable', Rule::enum(SourceTypeEnum::class)],
            'sampling_point' => ['nullable', Rule::enum(SamplingPointEnum::class)],
            'collected_by' => ['nullable', Rule::enum(CollectedByEnum::class)],
            'union_council_id' => ['nullable', 'integer', 'exists:union_councils,id'],
            'tehsil_id' => ['nullable', 'integer', 'exists:tehsils,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'starting_date' => ['nullable', 'date', 'date_format:Y-m-d', 'before:ending_date'],
            'ending_date' => ['nullable', 'date', 'date_format:Y-m-d', 'after:starting_date'],
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        if (isset($this->date_range)) {
            list($starting_date, $ending_date) = explode(' - ', $this->date_range);

            $this->merge([
                'starting_date' => $starting_date,
                'ending_date' => $ending_date,
            ]);
        }
    }
}
