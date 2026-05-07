<?php

namespace App\Http\Requests\DiaryDispatch;

use Illuminate\Foundation\Http\FormRequest;

class StoreDiaryDispatchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can(['add_diaries', 'add_dispatches']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'person_name' => ['required', 'string', 'max:255'],
            'date_on_letter' => ['required', 'date', 'date_format:Y-m-d'],
//            'receival_date' => ['required', 'date', 'date_format:Y-m-d'],
            'attachment_name' => ['required', 'string', 'max:255'],
            'attachment' => [
                'required',
                'file',
                'mimetypes:application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/msword,image/jpeg,image/png,image/jpg',
                'max:2048',
            ],
            'designation_id' => ['required', 'integer', 'exists:designations,id'],
            'folder_id' => ['required', 'integer', 'exists:folders,id'],
        ];
    }
}
