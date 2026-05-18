<?php

namespace App\Http\Requests\Issue;

use Illuminate\Foundation\Http\FormRequest;

class AssignIssueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $u = auth()->user();
        return $u && ($u->isUnscoped() || $u->can('add_issue_responsible') || $u->can('edit_issues'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            //
        ];
    }
}
