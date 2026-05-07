<?php

namespace App\Http\Requests\Search;

use App\Enums\AssetStatusEnum;
use App\Enums\MaterialStatusEnum;
use App\Enums\PurchaseOrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchPurchaseOrderRequest extends FormRequest
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
            'status' => ['nullable', Rule::in(PurchaseOrderStatus::values())],
            'starting_date' => ['nullable', 'date', 'before_or_equal:ending_date'],
            'ending_date' => ['nullable', 'date', 'after_or_equal:starting_date'],
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        if(isset($this->date_range)) {
            list($starting_date, $ending_date) = explode(' - ', $this->date_range);

            $this->merge([
                'starting_date' => $starting_date,
                'ending_date' => $ending_date,
            ]);
        }
    }
}
