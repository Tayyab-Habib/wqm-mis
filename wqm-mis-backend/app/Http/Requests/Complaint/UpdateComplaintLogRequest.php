<?php

namespace App\Http\Requests\Complaint;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateComplaintLogRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('edit_complaint_logs');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'complaint_id' => ['required', Rule::exists('complaints', 'id')],
            'comment' => ['required', 'string', 'max:65535'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png', 'mimetypes:image/jpeg,image/png'],
        ];
    }
}
