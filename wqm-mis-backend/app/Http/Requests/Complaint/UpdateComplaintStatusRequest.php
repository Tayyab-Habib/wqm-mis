<?php

namespace App\Http\Requests\Complaint;

use App\Enums\ComplaintStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateComplaintStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('edit_complaint_status');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'status' => ['required', Rule::in([ComplaintStatusEnum::IN_PROGRESS->value,
                ComplaintStatusEnum::RE_OPENED->value,
                ComplaintStatusEnum::CLOSED->value])],
        ];
    }
}
