<?php

namespace App\Http\Requests\Issue;

use App\Enums\ResponsibleTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIssueResponsibleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('add_issue_responsible');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'issue_id' => ['required', 'integer', 'exists:issues,id'],
            'details.*.responsible_id' => ['required', 'integer', 'exists:users,id'],
            'details.*.responsible_type' => ['required', Rule::enum(ResponsibleTypeEnum::class)]
        ];
    }
}
