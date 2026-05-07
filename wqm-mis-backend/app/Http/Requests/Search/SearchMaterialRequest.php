<?php

namespace App\Http\Requests\Search;

use App\Enums\AssetStatusEnum;
use App\Enums\MaterialStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchMaterialRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:255'],
            'starting_threshold' => ['nullable', 'numeric', 'required_with:starting_threshold', 'lte:ending_threshold'],
            'ending_threshold' => ['nullable', 'numeric', 'required_with:ending_threshold', 'gte:starting_threshold'],
            'status' => ['nullable', Rule::in(MaterialStatusEnum::values())],

        ];
    }
}
