<?php

namespace App\Http\Requests\Export;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportWaterSchemeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $u = auth()->user();
        return $u && ($u->isUnscoped() || $u->can('view_water_schemes'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'division_id' => ['nullable', Rule::exists('divisions', 'id')],
            'district_id' => ['nullable', Rule::exists('districts', 'id')],
            'tehsil_id' => ['nullable', Rule::exists('tehsils', 'id')],
            'source_type'=>['nullable', 'string', 'max:255'],
            'mode'=>['nullable', 'string', 'max:255'],
            'operation'=>['nullable', 'string', 'max:255'],
            'population'=>['nullable', 'string', 'max:255'],
            'type_of_machine'=>['nullable', 'string', 'max:255'],
            'capacity'=>['nullable', 'string', 'max:255'],
            'chamber'=>['nullable', 'string', 'max:255'],
        ];
    }
    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        if (isset($this->storage_range)) {
            list($start_from, $end_at) = explode(' - ', $this->storage_range);

            $this->merge([
                'start_from' => (int)$start_from,
                'end_at' => (int)$end_at,
            ]);

        }
    }
}
