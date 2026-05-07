<?php

namespace App\Http\Requests;

use App\Enums\DayEnum;
use App\Enums\FrequencyEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWaterSchemeScheduleRequest extends FormRequest
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
            'water_scheme_id' => ['required', Rule::exists('water_schemes', 'id')],
            'day_of_month' => ['required', $this->frequency === FrequencyEnum::WEEKLY->value ? Rule::in(DayEnum::values()) : ($this->frequency === FrequencyEnum::MONTHLY->value ? 'date_format:d' : 'date_format:m-d')],
            'frequency' => ['required', Rule::in(FrequencyEnum::values())],
            'is_recurring' => ['required', 'boolean']
        ];
    }
}
