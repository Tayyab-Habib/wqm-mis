<?php

namespace App\Http\Requests\Report;

use App\Enums\WaterSampleResultEnum;
use App\Rules\DistrictRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowWaterQualityAnalysisReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'month' => ['nullable', 'date', 'date_format:Y-m'],
            'from_date' => ['nullable', 'date'],
            'to_date'   => ['nullable', 'date', 'after_or_equal:from_date'],
            'division_id' => ['nullable', 'required_with:district_id', Rule::exists('divisions', 'id')],
            'district_id' => ['nullable', Rule::exists('districts', 'id'), new DistrictRule()],
            'result' => ['nullable', Rule::in(WaterSampleResultEnum::values())],
            'laboratory_id' => ['nullable', Rule::exists('laboratories', 'id')],
            'water_scheme_id' => ['nullable', Rule::exists('water_schemes', 'id')],
            'region_id' => ['nullable', Rule::exists('regions', 'id')],
            'circle_id' => ['nullable', Rule::exists('circles', 'id')],
            'phed_division_id' => ['nullable', Rule::exists('phed_divisions', 'id')],
        ];
    }
}
