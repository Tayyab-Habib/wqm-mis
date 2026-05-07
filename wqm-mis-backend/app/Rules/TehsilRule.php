<?php

namespace App\Rules;

use App\Models\Tehsil;
use Illuminate\Contracts\Validation\Rule;

class TehsilRule implements Rule
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
    public function passes($attribute, $value): bool
    {
        if ($value != null) {
            return $value
                && Tehsil::query()
                    ->where('id', '=', $value)
                    ->where('district_id', '=', request()->district_id)
                    ->exists();
        }
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return 'The selected tehsil does not belong to selected district.';
    }
}
