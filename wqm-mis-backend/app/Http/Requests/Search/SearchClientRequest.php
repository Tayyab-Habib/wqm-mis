<?php

namespace App\Http\Requests\Search;

use App\Enums\ClientTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'search_by' => ['nullable', 'string', 'max:255', Rule::in(['name', 'phone', 'organization'])],
            'organization_name' => ['nullable','required_if:search_by,' . ClientTypeEnum::ORGANIZATION->value, 'string', 'max:255'],
            'query' => ['nullable', 'string']
        ];
    }
}
