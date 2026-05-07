<?php

namespace App\Http\Requests\UnionCouncil;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnionCouncilRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('edit_union_councils');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'province_id' => ['required', 'exists:provinces,id', 'integer'],
            'division_id' => ['required', 'exists:divisions,id', 'integer'],
            'district_id' => ['required', 'exists:districts,id', 'integer'],
            'tehsil_id' => ['required', 'exists:tehsils,id', 'integer'],
            'name' => ['required', Rule::unique('union_councils')->where(function ($query) {
                $query->whereNull('deleted_at')
                    ->whereNot('id', '=', $this->union_council->id);
            }), 'max:255']
        ];
    }
}
