<?php

namespace App\Http\Requests\WaterSample;

use App\Enums\ClientTypeEnum;
use App\Enums\CollectableTypeEnum;
use App\Enums\CollectedByEnum;
use App\Enums\CollectedInEnum;
use App\Enums\DesiredTestEnum;
use App\Enums\ReasonForTestingEnum;
use App\Enums\SamplingPointEnum;
use App\Enums\SourceSubTypeEnum;
use App\Enums\SourceTypeEnum;
use App\Enums\TestFrequencyEnum;
use App\Enums\WaterSampleStatusEnum;
use App\Rules\DistrictRule;
use App\Rules\DivisionRule;
use App\Rules\TehsilRule;
use App\Rules\UnionCouncilRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class UpdateWaterSampleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('edit_water_samples');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        $currentDate = Carbon::now()->format('Y-m-d H:i:s');
        return [
            'region_id' => ['required', 'integer', 'exists:regions,id'],
            'division_id' => ['required', 'integer', 'exists:divisions,id', new DivisionRule()],
            'hub_lab_id' => ['required', 'integer', 'exists:hub_labs,id'],
            'circle_id' => ['required', 'integer', 'exists:circles,id'],
            'district_id' => ['required', 'integer', 'exists:districts,id', new DistrictRule()],
            'phed_division_id' => ['required', 'integer', 'exists:phed_divisions,id'],
            'water_scheme_id' => ['nullable', 'required_if:collectable_type,' . CollectableTypeEnum::PHE->value, Rule::exists('water_schemes', 'id')],
            'water_sample_address' => ['required', 'string', 'max:255'],
            'sample_name' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'test_type' => ['required', Rule::in(TestFrequencyEnum::values())],
            'collected_by' => ['required', Rule::in(CollectedByEnum::values())],
            'collected_in' => ['required', Rule::in(CollectedInEnum::values())],
            'collected_in_other' => ['nullable','required_if:collected_in,' . CollectedInEnum::OTHER->value, 'string', 'max:255'],
            'sampled_at' => ['required', 'date_format:Y-m-d H:i:s', 'before_or_equal:' . $currentDate],
            'temperature_in_celsius' => ['required', 'numeric', 'between:-5,50'],
            'source_type' => ['required', Rule::in(SourceTypeEnum::values())],
            'source_sub_type' => ['nullable', 'required_if:source_type,' .  SourceTypeEnum::PUMPING->value, Rule::in(SourceSubTypeEnum::values())],
            'sampling_point' => ['required', Rule::in(SamplingPointEnum::values())],
            'complaint' => ['required', Rule::in(ReasonForTestingEnum::values())],
            'complaint_by_other' => ['nullable','required_if:complaint,' . ReasonForTestingEnum::OTHER->value, 'string', 'max:255',],
        ];
    }
}
