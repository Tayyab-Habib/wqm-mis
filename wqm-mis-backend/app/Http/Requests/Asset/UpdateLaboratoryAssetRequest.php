<?php

namespace App\Http\Requests\Asset;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLaboratoryAssetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $u = auth()->user();
        return $u && ($u->isUnscoped() || $u->can('edit_assets'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            // SRS §2.7-3 equipment-specific fields stored on laboratory_assets.
            // All optional — partial updates are allowed.
            'make_model'        => ['nullable', 'string', 'max:255'],
            'serial_number'     => ['nullable', 'string', 'max:255'],
            'purchased_at'      => ['nullable', 'date_format:Y-m-d'],
            'warranty_expiry'   => ['nullable', 'date_format:Y-m-d'],
            'purchase_value'    => ['nullable', 'numeric', 'gte:0'],
            'calibration_cycle' => ['nullable', 'string', 'max:64'],
            'status'            => ['nullable', 'string', 'max:64'],
            'quantity'          => ['nullable', 'numeric', 'gte:0'],
            'unit'              => ['nullable', 'string', 'max:64'],
            'date_of_expiry'    => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
