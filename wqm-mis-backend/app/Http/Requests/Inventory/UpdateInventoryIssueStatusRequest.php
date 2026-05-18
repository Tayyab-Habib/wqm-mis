<?php

namespace App\Http\Requests\Inventory;

use App\Enums\InventoryDetailStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryIssueStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('edit_inventory_issue_status');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'status' => ['required', Rule::in([InventoryDetailStatusEnum::ISSUED->value])],
            'quantity' => ['required', 'integer', 'min:1'],
            'comment' => ['nullable', 'string', 'max:255'],
        ];
    }
}
