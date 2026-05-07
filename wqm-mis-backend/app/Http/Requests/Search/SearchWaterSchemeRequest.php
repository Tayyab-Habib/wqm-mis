<?php

namespace App\Http\Requests\Search;

use Illuminate\Foundation\Http\FormRequest;


class SearchWaterSchemeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return !!auth()?->id();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'union_council_id' => ['nullable', 'integer', 'exists:union_councils,id'],
            'tehsil_id' => ['nullable', 'integer', 'exists:tehsils,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
        ];
    }
}
