<?php

namespace App\Http\Requests\WaterSampleDetail;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTestReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('edit_test_report');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'remarks' => ['required', 'string', 'max:255'],
        ];
    }
}
