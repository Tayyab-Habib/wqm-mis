<?php

namespace App\Rules;

use App\Models\District;
use Illuminate\Contracts\Validation\Rule;

class DistrictRule implements Rule
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
                && District::query()
                    ->where('id', '=', $value)
                    ->where('circle_id', '=', request()->circle_id)
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
        return 'The selected district does not belong to selected circle.';
    }
}
