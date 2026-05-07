<?php

namespace App\Http\Requests\Issue;

use App\Enums\IssueStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIssueLogRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('add_issue_logs');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'issue_id' => ['required', 'exists:issues,id'],
            'comment' => ['required', 'string', 'max:65535'],
            'file' => ['nullable', 'image', 'mimes:jpeg,jpg,png', 'mimetypes:image/jpeg,image/png'],
            'status' => ['required', Rule::in([IssueStatusEnum::CLOSED->value, IssueStatusEnum::IN_PROGRESS->value, IssueStatusEnum::RE_OPENED->value])],
        ];
    }
}
