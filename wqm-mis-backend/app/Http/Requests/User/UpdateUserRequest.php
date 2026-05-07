<?php

namespace App\Http\Requests\User;

use App\Enums\EmploymentStatusEnum;
use App\Enums\GenderEnum;
use App\Rules\AgeOfConsentRule;
use App\Rules\DistrictRule;
use App\Rules\DivisionRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('edit_users');
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
            'email' => ['required', 'email:rfc,dns', Rule::unique('users')->where(function ($query) {
                $query->whereNull('deleted_at')
                    ->whereNot('id', '=', $this->user->id);
            }),
                'string', 'max:255'],
            'phone' => ['required', 'numeric', 'digits_between:10,11'],
            'image' => ['nullable', 'mimes:jpg,bmp,png', 'max:2048'],
            'gender' => ['required', Rule::in(GenderEnum::values())],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before:' . now()->format('Y-m-d'), new AgeOfConsentRule()],
            'date_of_joining' => ['nullable', 'date', 'date_format:Y-m-d'],
            'employee_status' => ['required', Rule::in(EmploymentStatusEnum::values())],
            'career_background' => ['required', 'string', 'max:65535'],
            'educational_background' => ['required', 'string', 'max:65535'],
            'basic_pay_scale' => ['required', 'integer', 'gte:0', 'max:2147483647'],
            'designation_id' => ['required', 'integer', 'exists:designations,id'],
            'laboratory_id' => ['nullable', 'integer', 'exists:laboratories,id'],
            'region_id' => [Rule::requiredIf(fn() => in_array($this->role, ['ce', 'se', 'xen'])), 'nullable', 'integer', 'exists:regions,id'],
            'circle_id' => [Rule::requiredIf(fn() => in_array($this->role, ['se', 'xen'])), 'nullable', 'integer', 'exists:circles,id'],
            'phed_division_id' => [Rule::requiredIf(fn() => $this->role === 'xen'), 'nullable', 'integer', 'exists:phed_divisions,id'],
            'district_id' => ['required', 'integer', 'exists:districts,id', new DistrictRule()],
            'division_id' => ['required', 'integer', 'exists:divisions,id', new DivisionRule()],
            'province_id' => ['required', 'integer', 'exists:provinces,id'],
            'present_duty' => ['required_with:laboratory_id,', 'string', 'max:255'],
            'assigned_parameters' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::exists('roles', 'name')],
            'password' => ['nullable', 'string', Password::min(8)->letters()->mixedCase()->numbers()->symbols()->uncompromised()]
        ];
    }
}
