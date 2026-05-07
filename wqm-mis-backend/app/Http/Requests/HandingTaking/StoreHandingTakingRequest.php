<?php

namespace App\Http\Requests\HandingTaking;

use App\Enums\IssueTypeEnum;
use App\Rules\MorphedRelationArrayRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHandingTakingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
        return auth()->user()->can('add_handing_takings');
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
            'stockable_type' => ['required', Rule::in([IssueTypeEnum::STOCK->value, IssueTypeEnum::INVENTORY->value])],
            'stockable_id' => [
                'required',
                'numeric',
                $this->stockable_type === IssueTypeEnum::STOCK->value
                    ? 'exists:laboratory_materials,id'
                    : 'exists:laboratory_assets,id',
            ],
            'quantity' => ['required', 'decimal:2'],
            'unit' => ['required', 'string', 'max:255'],
            'laboratory_user_id'=> ['required', Rule::exists('users', 'id')]
        ];
    }

    public function messages()
    {
        return [
            'quantity.decimal' => 'The :attribute must be a valid decimal number',
        ];
    }
}
