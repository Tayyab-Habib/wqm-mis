<?php

namespace App\Http\Requests\Report;

use App\Rules\FinancialYearRule;
use Illuminate\Foundation\Http\FormRequest;

class InvokeCentralLaboratoryWaterQualityReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $u = auth()->user();
        return $u && ($u->isUnscoped() || $u->can('central_water_analysis_report') || $u->can('view_reports'));
    }

    protected function prepareForValidation()
    {
        if (!empty($this->year)) {
            $years = explode('-', str_replace(' ', '', $this->year));

            if (count($years) == 2) {
                $this->merge([
                    'start_year' => (int)$years[0],
                    'end_year' => (int)$years[1]
                ]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'start_year' => ['required', 'date_format:Y', 'lt:end_year', new FinancialYearRule($this->start_year, $this->end_year)],
            'end_year' => ['required', 'date_format:Y', 'gt:start_year'],
        ];
    }
}
