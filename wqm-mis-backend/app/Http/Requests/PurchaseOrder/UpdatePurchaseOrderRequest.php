<?php

namespace App\Http\Requests\PurchaseOrder;

use App\Enums\IssueTypeEnum;
use App\Enums\PurchaseOrderStatus;
use App\Rules\MorphedRelationArrayRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePurchaseOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('edit_purchase_orders');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'date_of_order' => ['required', 'date_format:Y-m-d'],
            'details.*.purchasable_type' => ['required', Rule::in([IssueTypeEnum::STOCK->value, IssueTypeEnum::INVENTORY->value])],
            'details.*.purchasable_id' => [
                'required',
                'numeric',
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
