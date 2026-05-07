<?php

namespace App\Http\Requests\Search;

use App\Enums\ComplaintStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchComplaintRequest extends FormRequest
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
            'complaint_type_id' => ['nullable', Rule::exists('complaint_types', 'id')],
            'status' => ['nullable', Rule::in(ComplaintStatusEnum::values())],
        ];
    }
}
