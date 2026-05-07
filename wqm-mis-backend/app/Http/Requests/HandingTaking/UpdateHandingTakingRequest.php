<?php

namespace App\Http\Requests\HandingTaking;

use App\Enums\IssueTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHandingTakingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('edit_handing_takings');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'description' => ['required', 'string', 'max:65535'],
            'stockable_type' => ['required', Rule::in([IssueTypeEnum::MATERIAL->value, IssueTypeEnum::ASSET->value])],
            'stockable_id' => [
                'required',
                'numeric',
                $this->stockable_type === IssueTypeEnum::MATERIAL->value
                    ? 'exists:materials,id'
                    : 'exists:assets,id',
            ],
            'quantity' => ['required', 'decimal:2'],
            'unit' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages()
    {
        return [
            'quantity.decimal' => 'The :attribute must be a valid decimal number',
        ];
    }
}
