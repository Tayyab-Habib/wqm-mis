<?php

namespace App\Http\Requests\Inventory;

use App\Enums\IssueTypeEnum;
use App\Rules\MorphedRelationArrayRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('add_inventories');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'details.*.inventoryable_type' => ['required', Rule::in([IssueTypeEnum::STOCK->value, IssueTypeEnum::INVENTORY->value])],
            'details.*.inventoryable_id' => [
                'required',
                'integer',
                new MorphedRelationArrayRule()
            ],
            'details.*.quantity' => ['required', 'decimal:2'],
            'details.*.unit' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages()
    {
        return [
            'details.*.quantity.decimal' => 'The quantity must be a valid decimal number'
        ];
    }
}
