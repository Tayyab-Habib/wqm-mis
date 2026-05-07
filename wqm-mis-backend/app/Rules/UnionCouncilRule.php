<?php

namespace App\Rules;

use App\Models\UnionCouncil;
use Illuminate\Contracts\Validation\Rule;

class UnionCouncilRule implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if ($value != null) {
            return $value
                && UnionCouncil::query()
                    ->where('id', '=', $value)
                    ->where('tehsil_id', '=', request()->tehsil_id)
                    ->exists();
        }
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The selected union council does not belong to selected tehsil.';
    }
}
