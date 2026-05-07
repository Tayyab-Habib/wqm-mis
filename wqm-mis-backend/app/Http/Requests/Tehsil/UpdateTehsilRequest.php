<?php

namespace App\Http\Requests\Tehsil;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTehsilRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('edit_tehsils');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'district_id' => ['required', 'exists:districts,id', 'integer'],
            'name' => ['required', Rule::unique('tehsils')->where(function ($query) {
                $query->whereNull('deleted_at')
                    ->whereNot('id', '=', $this->tehsil->id);
            }), 'max:255']
        ];
    }
}
