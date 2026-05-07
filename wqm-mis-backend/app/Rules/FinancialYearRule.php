<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class FinancialYearRule implements Rule
{
    protected ?int $startYear;
    protected ?int $endYear;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($startYear, $endYear)
    {
        $this->startYear = $startYear;
        $this->endYear = $endYear;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return 1 === $this->endYear - $this->startYear;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'You have entered invalid financial year.';
    }
}
