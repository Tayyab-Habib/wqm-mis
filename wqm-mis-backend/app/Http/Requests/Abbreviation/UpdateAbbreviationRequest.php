<?php

namespace App\Http\Requests\Abbreviation;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAbbreviationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('edit_abbreviations');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => ['required', 'unique:abbreviations,name,' . $this->abbreviation->id, 'max:255'],
            'detail' => ['required', 'string', 'max:65535']
        ];
    }
}
