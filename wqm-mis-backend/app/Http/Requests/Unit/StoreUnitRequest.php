<?php

namespace App\Http\Requests\Unit;

use App\Enums\InvoiceableTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('add_units');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => [
                'required',
                Rule::unique('units')->where(function ($query) {
                    $query->whereNull('deleted_at');
                }),
                'string',
                'max:255'
            ],
            'type' => [
                'required',
                Rule::in([
                        InvoiceableTypeEnum::STOCK->value,
                        InvoiceableTypeEnum::INVENTORY->value
                    ]
                )]
        ];
    }
}
