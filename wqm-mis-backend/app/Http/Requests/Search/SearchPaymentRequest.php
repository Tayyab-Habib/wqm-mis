<?php

namespace App\Http\Requests\Search;

use App\Enums\AssetStatusEnum;
use App\Enums\MaterialStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchPaymentRequest extends FormRequest
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
            'starting_amount' => ['nullable', 'numeric', 'required_with:starting_amount', 'lte:ending_amount'],
            'ending_amount' => ['nullable', 'numeric', 'required_with:ending_amount', 'gte:starting_amount'],
        ];
    }
}
