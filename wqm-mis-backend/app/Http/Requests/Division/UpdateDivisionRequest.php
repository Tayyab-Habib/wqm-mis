<?php

namespace App\Http\Requests\Division;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDivisionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('edit_divisions');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'province_id' => ['required', 'exists:provinces,id', 'integer'],
            'abbreviation' => ['required',  Rule::unique('divisions')->where(function ($query) {
                $query->whereNull('deleted_at')
                    ->whereNot('id', '=', $this->division->id);
            }), 'string', 'max:255'],
            'name' => ['required', Rule::unique('divisions')->where(function ($query) {
                $query->whereNull('deleted_at')
                    ->whereNot('id', '=', $this->division->id);
            }), 'max:255']
        ];
    }
}
