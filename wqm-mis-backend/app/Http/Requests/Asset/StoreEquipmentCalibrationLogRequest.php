<?php

namespace App\Http\Requests\Asset;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEquipmentCalibrationLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'laboratory_asset_id' => ['required', 'integer', 'exists:laboratory_assets,id'],
            'calibration_date'    => ['required', 'date'],
            'calibrated_by'       => ['required', 'string', 'max:255'],
            'result'              => ['required', 'string', Rule::in(['Pass', 'Conditional Pass', 'Fail'])],
            'certificate_ref'     => ['nullable', 'string', 'max:255'],
            'standard_used'       => ['nullable', 'string', 'max:255'],
            'next_due_date'       => ['nullable', 'date'],
            'remarks'             => ['nullable', 'string', 'max:1000'],
        ];
    }
}
