<?php

namespace App\Http\Requests\WaterScheme;

use App\Rules\DistrictRule;
use App\Rules\DivisionRule;
use App\Rules\TehsilRule;
use App\Rules\UnionCouncilRule;
use Illuminate\Foundation\Http\FormRequest;

class ShowWaterSchemeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('show_water_schemes');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            //
        ];
    }
}
