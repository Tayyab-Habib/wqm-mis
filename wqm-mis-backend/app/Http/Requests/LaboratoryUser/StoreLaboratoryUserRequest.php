<?php

namespace App\Http\Requests\LaboratoryUser;

use Illuminate\Foundation\Http\FormRequest;

class StoreLaboratoryUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('add_laboratory_users');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'present_duty' => ['required', 'string', 'max:255'],
            'assigned_parameters' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
