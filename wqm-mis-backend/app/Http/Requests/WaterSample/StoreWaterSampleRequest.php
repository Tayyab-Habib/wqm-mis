<?php

namespace App\Http\Requests\WaterSample;

use App\Enums\ClientTypeEnum;
use App\Enums\CollectableTypeEnum;
use App\Enums\CollectedByEnum;
use App\Enums\CollectedInEnum;
use App\Enums\DesiredTestEnum;
use App\Enums\OnDemandTestEnum;
use App\Enums\ReasonForTestingEnum;
use App\Enums\SamplingPointEnum;
use App\Enums\SourceSubTypeEnum;
use App\Enums\SourceTypeEnum;
use App\Enums\TestFrequencyEnum;
use App\Enums\WaterSampleStatusEnum;
use App\Rules\DistrictRule;
use App\Rules\DivisionRule;
use App\Rules\TehsilRule;
use App\Rules\TemperatureRangeRule;
use App\Rules\UnionCouncilRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StoreWaterSampleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('add_water_samples');
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
            'test_type' => ['required', Rule::in(TestFrequencyEnum::values())],
            'water_scheme_id' => ['nullable', 'required_if:collectable_type,' . CollectableTypeEnum::PHE->value, 'integer', 'exists:water_schemes,id'],
            'water_sample_address' => ['required', 'string', 'max:255'],
            'sample_name' => ['required', 'string', 'max:255'],
            'source_type' => ['required', Rule::in(SourceTypeEnum::values())],
            'source_sub_type' => ['nullable', 'required_if:source_type,' . SourceTypeEnum::PUMPING->value, Rule::in(SourceSubTypeEnum::values())],
            'sampling_point' => ['required', Rule::in(SamplingPointEnum::values())],
            'collected_by' => ['required', Rule::in(CollectedByEnum::values())],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            //'status' => ['required', Rule::in(WaterSampleStatusEnum::values())],
            'temperature_in_celsius' => ['required', 'numeric', 'between:-5,50'],
            'sampled_at' => ['required', 'date_format:Y-m-d H:i:s', 'before_or_equal:' . $currentDate],
            'reported_at' => ['required', 'date_format:Y-m-d H:i:s', 'after_or_equal:sampled_at'],
            'analyzed_at' => ['nullable', 'date_format:Y-m-d H:i:s', 'before_or_equal:' . $currentDate],
            'collected_in' => ['required', Rule::in(CollectedInEnum::values())],
            'collected_in_other' => ['nullable', 'required_if:collected_in,' . CollectedInEnum::OTHER->value, 'string', 'max:255',],
            'complaint' => ['required', Rule::in(ReasonForTestingEnum::values())],
            'complaint_by_other' => ['nullable', 'required_if:complaint,' . ReasonForTestingEnum::OTHER->value, 'string', 'max:255',],
            'desired_test' => ['required', 'array'],
            'desired_test.*' => ['required', Rule::in(DesiredTestEnum::values())],
            'province_id' => ['required', 'integer', 'exists:provinces,id'],
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'division_id' => ['required', 'integer', 'exists:divisions,id', new DivisionRule()],
            'hub_lab_id' => ['nullable', 'integer', 'exists:hub_labs,id'],
            'circle_id' => ['nullable', 'integer', 'exists:circles,id'],
            'district_id' => ['required', 'integer', 'exists:districts,id', new DistrictRule()],
            'phed_division_id' => ['nullable', 'integer', 'exists:phed_divisions,id'],
            'collectable_type' => ['required', Rule::in(CollectableTypeEnum::values())],
            'name' => ['required_if:collectable_type,' . CollectableTypeEnum::PRIVATE ->value, 'string', 'max:255'],
            'phone' => ['required_if:collectable_type,' . CollectableTypeEnum::PRIVATE ->value, 'numeric', 'digits_between:10,11'],
            'email' => ['nullable', 'string', 'email:rfc,dns', 'max:255'],
            'address' => ['required_if:collectable_type,' . CollectableTypeEnum::PRIVATE ->value, 'string', 'max:255'],
            'type' => ['required_if:collectable_type,' . CollectableTypeEnum::PRIVATE ->value, Rule::in(ClientTypeEnum::values())],
            'organization_name' => ['max:255'],
            'on_demand_tests.*' => ['nullable', Rule::in(OnDemandTestEnum::values())],
        ];
    }
}
