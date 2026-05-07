<?php

namespace App\Http\Requests\Issue;

use App\Enums\IssueTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIssueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('edit_issues');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'issuable_type' => ['required', Rule::in(IssueTypeEnum::values())],
            'issuable_id' => [
                'required',
                'numeric',
                $this->issuable_type === IssueTypeEnum::LABORATORY->value
                    ? 'exists:laboratories,id'
                    : ($this->issuable_type === IssueTypeEnum::COMPLAINT->value
                    ? 'exists:complaints,id'
                    : ($this->issuable_type === IssueTypeEnum::STOCK->value
                        ? 'exists:materials,id'
                        : 'exists:assets,id')),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:65535'],
            'file' => ['nullable', 'image', 'mimes:jpeg,jpg,png', 'mimetypes:image/jpeg,image/png'],
        ];
    }
}
