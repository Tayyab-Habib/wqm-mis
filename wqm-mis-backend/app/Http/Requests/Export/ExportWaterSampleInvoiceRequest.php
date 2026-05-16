<?php

namespace App\Http\Requests\Export;

use App\Enums\WaterSampleInvoiceStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportWaterSampleInvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $u = auth()->user();
        return $u && ($u->isUnscoped() || $u->can('view_invoices') || $u->can('show_water_sample_invoices'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'district_id' => ['nullable', Rule::exists('districts', 'id')],
            'tehsil_id' => ['nullable', Rule::exists('tehsils', 'id')],
            'laboratory_id' => ['nullable', Rule::exists('laboratories', 'id')],
            'status' => ['nullable', Rule::enum(WaterSampleInvoiceStatusEnum::class)],
            'min_price' => ['nullable', 'numeric', 'lt:max_price'],
            'max_price' => ['nullable', 'numeric', 'gt:min_price'],
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        if (isset($this->price_range)) {
            list($minPrice, $maxPrice) = explode(' - ', $this->price_range);

            $this->merge([
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
            ]);
        }
    }
}
