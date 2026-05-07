<?php

namespace App\Http\Requests\Complaint;

use App\Enums\ComplaintTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateComplaintRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('edit_complaints');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'complaint_type_id' => ['required', 'integer', Rule::exists('complaint_types', 'id')],
            'description' => ['required', 'string', 'max:65535'],
            'title' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png', 'mimetypes:image/jpeg,image/png'],
        ];
    }
}
