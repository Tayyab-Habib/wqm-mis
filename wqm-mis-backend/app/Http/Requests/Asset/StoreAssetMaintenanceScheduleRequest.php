<?php

namespace App\Http\Requests\Asset;

use App\Enums\AssetMaintenanceTypeEnum;
use App\Enums\DayEnum;
use App\Enums\FrequencyEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetMaintenanceScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('add_asset_maintenance_schedules');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'laboratory_asset_id' => ['required', 'integer', Rule::exists('laboratory_assets', 'id')],
            'day_of_month' => ['required', $this->frequency === FrequencyEnum::WEEKLY->value ? Rule::in(DayEnum::values()) : 'date_format:m-d'],
            'frequency' => ['required', Rule::in(FrequencyEnum::values())],
            'is_recurring' => ['required', 'boolean'],
            'type' => ['required', Rule::in(AssetMaintenanceTypeEnum::array())],
        ];
    }
}
