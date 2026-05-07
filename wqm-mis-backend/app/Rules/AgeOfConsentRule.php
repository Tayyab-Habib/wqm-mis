<?php

namespace App\Rules;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\Rule;

class AgeOfConsentRule implements Rule
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
        return $this->ageOfConsent($value);
    }

    /**
     * Checks through validation methods to verify its age is greater than 18 years of age
     *
     * @param $value
     * @return boolean check if age of user is greater than 18
     */
    private function ageOfConsent($value): bool
    {
        $currentDate = Carbon::now()->format('Y-m-d');
        return Carbon::parse($value)->diff($currentDate)->y >= 18;
    }

    public function message()
    {
        return 'Age should be greater than or equal to 18 years';
    }
}
