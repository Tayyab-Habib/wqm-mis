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
        // Consistency check (not a presence check). Pass when:
        //  1. district_id wasn't provided.
        //  2. circle_id wasn't provided (no two-sided comparison possible).
        //  3. The district's own circle_id is NULL — upstream data gap; PHED still
        //     populating districts.circle_id. Until that mapping lands, we can't
        //     enforce the relationship.
        if ($value === null || $value === '') return true;
        $circleId = request()->circle_id;
        if ($circleId === null || $circleId === '') return true;
        $district = District::query()->find($value);
        if (!$district) return false;
        if ($district->circle_id === null) return true;
        return (int) $district->circle_id === (int) $circleId;
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
