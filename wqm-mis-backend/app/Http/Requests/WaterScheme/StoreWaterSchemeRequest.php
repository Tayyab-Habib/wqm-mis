<?php

namespace App\Http\Requests\WaterScheme;

use App\Enums\PowerInputEnum;
use App\Rules\DistrictRule;
use App\Rules\DivisionRule;
use App\Rules\TehsilRule;
use App\Rules\UnionCouncilRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWaterSchemeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('add_water_schemes');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => ['required', Rule::unique('water_schemes')->where(function ($query) {
                $query->whereNull('deleted_at');
            }), 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'address' => ['required', 'string', 'max:255'],
            'source_type' => ['nullable', 'string'],
            'years_of_installation' => ['nullable', 'numeric'],
            'mode' => ['nullable', 'string'],
            'operation' => ['nullable', 'string'],
            'power_input' => ['nullable', Rule::enum(PowerInputEnum::class)],
            'type_of_machine' => ['nullable', 'string'],
            'horse_power_motor' => ['nullable', 'string'],
            'storage' => ['nullable', 'string'],
            'capacity' => ['nullable', 'string'],
            'depth' => ['nullable', 'string'],
            'population' => ['nullable', 'string'],
            'chamber' => ['nullable', 'string'],
            'pipe_type' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
            'union_council_id' => ['nullable', 'exists:union_councils,id', new UnionCouncilRule()],
            'tehsil_id' => ['required', 'exists:tehsils,id', new TehsilRule()],
            'district_id' => ['required', 'exists:districts,id', new DistrictRule()],
            'division_id' => ['required', 'exists:divisions,id', new DivisionRule()],
            'province_id' => ['required', 'exists:provinces,id'],
            'phed_division_id' => ['nullable', 'exists:phed_divisions,id'],
        ];
    }
}
