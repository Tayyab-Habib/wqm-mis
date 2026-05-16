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
        // Consistency check (not a presence check). Pass when:
        //  1. division_id wasn't provided (Laravel's `nullable` handles existence).
        //  2. region_id wasn't provided (no two-sided comparison possible).
        //  3. The division's own region_id is NULL — upstream data gap; PHED still
        //     populating divisions.region_id. Until that mapping lands, we can't
        //     enforce the relationship for those divisions. Once it lands, the
        //     rule starts enforcing automatically.
        if ($value === null || $value === '') return true;
        $regionId = request()->region_id;
        if ($regionId === null || $regionId === '') return true;
        $division = Division::query()->find($value);
        if (!$division) return false;                          // bad division id → fail (exists rule should already catch)
        if ($division->region_id === null) return true;        // upstream data gap → can't enforce, allow
        return (int) $division->region_id === (int) $regionId; // both sides known → must match
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
