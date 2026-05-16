<?php

namespace App\Http\Requests\Export;

use App\Enums\CollectableTypeEnum;
use App\Enums\CollectedByEnum;
use App\Enums\CollectedInEnum;
use App\Enums\DesiredTestEnum;
use App\Enums\ReasonForTestingEnum;
use App\Enums\SamplingPointEnum;
use App\Enums\SourceTypeEnum;
use App\Enums\TestFrequencyEnum;
use App\Enums\TestTypeEnum;
use App\Enums\WaterSampleResultEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportWaterSampleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $u = auth()->user();
        return $u && ($u->isUnscoped() || $u->can('view_water_samples'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'district_id' => ['nullable', Rule::exists('districts', 'id')],
            'division_id' => ['nullable', Rule::exists('divisions', 'id')],
            'water_scheme_id' => ['nullable', Rule::exists('water_schemes', 'id')],
            'tehsil_id' => ['nullable', Rule::exists('tehsils', 'id')],
            'laboratory_id' => ['nullable', Rule::exists('laboratories', 'id')],
            'source_type' => ['nullable', Rule::enum(SourceTypeEnum::class)],
            'result' => ['nullable', Rule::enum(WaterSampleResultEnum::class)],
            'sampling_point' => ['nullable', Rule::enum(SamplingPointEnum::class)],
            'collected_by' => ['nullable', Rule::enum(CollectedByEnum::class)],
            'collectable_type' => ['nullable', Rule::enum(CollectableTypeEnum::class)],
            'collected_in' => ['nullable', Rule::enum(CollectedInEnum::class)],
            'complaint' => ['nullable', Rule::enum(ReasonForTestingEnum::class)],
            'union_council_id' => ['nullable', 'integer', 'exists:union_councils,id'],
            'starting_date' => ['nullable', 'date', 'date_format:Y-m-d', 'before:ending_date'],
            'ending_date' => ['nullable', 'date', 'date_format:Y-m-d', 'after:starting_date'],
            'desired_test.*' => ['nullable', Rule::enum(DesiredTestEnum::class)],
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
