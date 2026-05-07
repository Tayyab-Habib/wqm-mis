<?php

namespace App\Http\Requests\Issue;

use App\Enums\IssueTypeEnum;
use App\Enums\ResponsibleTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIssueResponsibleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('edit_issue_responsible');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'responsible_type.*' => ['required', Rule::enum(ResponsibleTypeEnum::class)]
        ];
    }
}
