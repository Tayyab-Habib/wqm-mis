<?php

namespace App\Http\Requests\User;

use App\Rules\AgeOfConsentRule;
use App\Rules\DistrictRule;
use App\Rules\DivisionRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // Own profile — anyone authenticated can update their own data.
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required',
                'email:rfc,dns', Rule::unique('users')->where(function ($query) {
                    $query->whereNull('deleted_at')
                        ->whereNot('id', '=', auth()->user()->id);
                }),
                'string', 'max:255'],
            'phone' => ['required', 'numeric', 'digits_between:10,11'],
            'image' => ['nullable', 'mimes:jpg,bmp,png', 'max:2048'],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before:' . now()->format('Y-m-d'), new AgeOfConsentRule()],
            'career_background' => ['required', 'string', 'max:65535'],
            'educational_background' => ['required', 'string', 'max:65535'],
            'designation_id' => ['required', 'integer', 'exists:designations,id'],
            'district_id' => ['required', 'integer', 'exists:districts,id', new DistrictRule()],
            'division_id' => ['required', 'integer', 'exists:divisions,id', new DivisionRule()],
            'province_id' => ['required', 'integer', 'exists:provinces,id'],
        ];
    }
}
