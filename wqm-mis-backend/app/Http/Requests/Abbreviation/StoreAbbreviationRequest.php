<?php

namespace App\Http\Requests\Abbreviation;

use Illuminate\Foundation\Http\FormRequest;

class StoreAbbreviationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('add_abbreviations');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => ['required', 'unique:abbreviations', 'max:255'],
            'detail' => ['required', 'string', 'max:65535']
        ];
    }
}
