<?php

namespace App\Rules;

use App\Models\Division;
use Illuminate\Contracts\Validation\Rule;

class DivisionRule implements Rule
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
                && Division::query()
                    ->where('id', '=', $value)
                    ->where('region_id', '=', request()->region_id)
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
        return 'The selected division does not belong to selected region.';
    }
}
