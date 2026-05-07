<?php

namespace App\Http\Requests;

use App\Enums\CollectableTypeEnum;
use App\Enums\DurationEnum;
use App\Enums\OnDemandTestEnum;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardRequest extends FormRequest
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
            'type' => ['nullable', 'string', Rule::in(CollectableTypeEnum::values())],
            'duration' => ['nullable', 'string', Rule::enum(DurationEnum::class)],
            'annual.*' => ['nullable', 'date_format:Y'],
            'annual' => ['required_if:duration,' . DurationEnum::ANNUAL->value],
            'quarterly' => ['nullable', Rule::in(['Q1', 'Q2', 'Q3', 'Q4'])], //TODO: will discuss later
            'start_month' => ['nullable', 'required_if:duration,' . DurationEnum::MONTH->value, 'date', 'date_format:Y-m-d', 'before:end_month'], //date range [2023-01-01, 2023-01-05]
            'end_month' => ['nullable', 'required_if:duration,' . DurationEnum::MONTH->value, 'date', 'date_format:Y-m-d', 'after:start_month'], //date range [2023-01-01, 2023-01-05]
            'division_id' => ['nullable', 'required_with:district_id', Rule::exists('divisions', 'id')],
            'district_id' => ['nullable', Rule::exists('districts', 'id')],
            'laboratory_id' => ['nullable', Rule::exists('laboratories', 'id')],
            'on_demand_tests.*' => ['nullable', Rule::in(OnDemandTestEnum::values())],
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
            $this->merge([
                'start_month' => Carbon::parse($this->date_range[0])->format('Y-m-d'),
                'end_month' => Carbon::parse($this->date_range[1])->format('Y-m-d'),
            ]);
        }
    }
}
