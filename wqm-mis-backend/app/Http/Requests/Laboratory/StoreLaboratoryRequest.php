<?php

namespace App\Http\Requests\Laboratory;

use App\Rules\DistrictRule;
use App\Rules\DivisionRule;
use App\Rules\TehsilRule;
use App\Rules\UnionCouncilRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLaboratoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('add_laboratories');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => ['required', Rule::unique('laboratories')->where(function ($query) {
                $query->whereNull('deleted_at');
            }), 'max:255'],
            'present_duty' => ['required', 'string', 'max:255'],
            'assigned_parameters' => ['nullable', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'phone' => ['required', 'numeric', 'digits_between:10,11'],
            'fax' => ['required', 'numeric', 'digits_between:10,11'],
            'email' => ['required', 'email:rfc,dns', Rule::unique('laboratories')->where(function ($query) {
                $query->whereNull('deleted_at');
            }),
                'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'mimes:jpg,bmp,png', 'max:2048'],
            'focal_person_id' => ['required', 'exists:users,id'],
            'union_council_id' => ['nullable', 'exists:union_councils,id', new UnionCouncilRule()],
            'tehsil_id' => ['nullable', 'exists:tehsils,id', new TehsilRule()],
            'district_id' => ['required', 'exists:districts,id', new DistrictRule()],
            'division_id' => ['required', 'exists:divisions,id', new DivisionRule()],
            'province_id' => ['required', 'exists:provinces,id'],
            'covered_districts' => ['required','array', 'exists:districts,id'],
        ];
    }
}
