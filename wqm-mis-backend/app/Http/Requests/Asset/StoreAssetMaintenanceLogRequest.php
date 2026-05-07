<?php

namespace App\Http\Requests\Asset;

use App\Enums\AssetMaintenanceStatusEnum;
use App\Enums\IssueStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetMaintenanceLogRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('add_asset_maintenance_logs');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'id' => ['required', 'exists:asset_maintenance_schedule_logs,id'],
            'asset_maintenance_schedule_id' => ['required', 'exists:asset_maintenance_schedules,id'],
            'comment' => ['required', 'string', 'max:1000'],
            'file' => ['sometimes', 'image', 'mimes:jpeg,jpg,png', 'mimetypes:image/jpeg,image/png'],
            'status' => ['required', Rule::in([AssetMaintenanceStatusEnum::UNDER_SERVICE->value,
                AssetMaintenanceStatusEnum::DELAYED->value,
                AssetMaintenanceStatusEnum::SERVICED->value,
                AssetMaintenanceStatusEnum::BROKEN->value])],
        ];
    }
}
