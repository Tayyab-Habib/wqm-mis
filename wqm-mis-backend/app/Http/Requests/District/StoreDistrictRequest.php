<?php

namespace App\Http\Requests\District;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDistrictRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('add_districts');
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
            'division_id' => ['required', 'exists:divisions,id', 'integer'],
            'name' => ['required', Rule::unique('districts')->where(function ($query) {
                $query->whereNull('deleted_at');
            }),
                'string', 'max:255']
        ];
    }
}
