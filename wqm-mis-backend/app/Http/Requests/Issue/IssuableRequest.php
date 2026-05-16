<?php

namespace App\Http\Requests\Issue;

use App\Enums\IssueTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssuableRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // Issuable list is used to populate dropdowns of things issues can be
        // raised against — gated on view_issues so any role granted issue
        // visibility can fill the dropdown.
        $u = auth()->user();
        return $u && ($u->isUnscoped() || $u->can('view_issues'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'issuable_type' => [
                'required',
                Rule::in(IssueTypeEnum::values())]
        ];
    }
}
