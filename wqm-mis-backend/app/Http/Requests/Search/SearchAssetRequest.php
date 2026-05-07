<?php

namespace App\Http\Requests\Search;

use App\Enums\AssetStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchAssetRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:255'],
            'starting_threshold' => ['nullable', 'numeric', 'gte:0', 'required_with:starting_threshold', 'lte:ending_threshold'],
            'ending_threshold' => ['nullable', 'numeric', 'gte:0', 'required_with:ending_threshold', 'gte:starting_threshold'],
            'starting_date' => ['nullable', 'date', 'before:ending_date'],
            'ending_date' => ['nullable', 'date', 'after:starting_date'],
            'status' => ['nullable', Rule::in(AssetStatusEnum::values())],

        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        if (isset($this->date_range)) {
            list($starting_date, $ending_date) = explode(' - ', $this->date_range);

            $this->merge([
                'starting_date' => $starting_date,
                'ending_date' => $ending_date,
            ]);
        }
    }
}
