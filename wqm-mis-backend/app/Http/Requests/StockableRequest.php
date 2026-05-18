<?php

namespace App\Http\Requests;

use App\Enums\InvoiceableTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockableRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // Stockable list populates dropdowns (assets / materials) for stock
        // operations — gated on whichever read perm the user holds.
        $u = auth()->user();
        return $u && ($u->isUnscoped() || $u->can('view_assets') || $u->can('view_material') || $u->can('view_inventories'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'stockable_type' => ['required', Rule::in(InvoiceableTypeEnum::values())]
        ];
    }
}
