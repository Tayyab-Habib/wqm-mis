<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Drop-in replacement for `'col' => SomeEnum::class` that case-insensitively
 * resolves backing values. Created to silence the run-time 500s caused by
 * existing rows containing lowercase variants of enum cases (e.g. 'fresh'
 * vs `TestFrequencyEnum::FRESH = 'Fresh'`) — see D-02 / F-13 audit.
 *
 * Use:
 *
 *   protected $casts = [
 *       'test_type' => TolerantEnumCast::class . ':' . TestFrequencyEnum::class,
 *   ];
 */
class TolerantEnumCast implements CastsAttributes
{
    public function __construct(
        protected string $enumClass,
    ) {
        if (!enum_exists($enumClass)) {
            throw new \InvalidArgumentException("Not an enum: $enumClass");
        }
    }

    public function get($model, string $key, $value, array $attributes)
    {
        if ($value === null) {
            return null;
        }
        // Exact match first
        $match = ($this->enumClass)::tryFrom((string) $value);
        if ($match) {
            return $match;
        }
        // Case-insensitive fallback
        $needle = strtolower((string) $value);
        foreach (($this->enumClass)::cases() as $case) {
            if (strtolower($case->value) === $needle) {
                return $case;
            }
        }
        return null;
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof $this->enumClass) {
            return $value->value;
        }
        // Accept strings — normalize via the same case-insensitive matcher
        $match = ($this->enumClass)::tryFrom((string) $value);
        if ($match) {
            return $match->value;
        }
        $needle = strtolower((string) $value);
        foreach (($this->enumClass)::cases() as $case) {
            if (strtolower($case->value) === $needle) {
                return $case->value;
            }
        }
        // Last resort: store as-is and let the DB constraint (if any) decide.
        return (string) $value;
    }
}
