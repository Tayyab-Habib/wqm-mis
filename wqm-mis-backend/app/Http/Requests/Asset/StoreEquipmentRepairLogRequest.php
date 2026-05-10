<?php

namespace App\Http\Requests\Asset;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEquipmentRepairLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'laboratory_asset_id' => ['required', 'integer', 'exists:laboratory_assets,id'],
            'fault_date'          => ['required', 'date'],
            'fault_description'   => ['required', 'string', 'max:1000'],
            'repair_status'       => ['required', 'string', Rule::in(['Reported', 'Under Repair', 'Resolved', 'Beyond Repair'])],
            'technician'          => ['nullable', 'string', 'max:255'],
            'resolved_date'       => ['nullable', 'date'],
            'repair_cost'         => ['nullable', 'numeric', 'min:0'],
            'remarks'             => ['nullable', 'string', 'max:1000'],
        ];
    }
}
