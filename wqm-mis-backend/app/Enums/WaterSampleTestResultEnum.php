<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum WaterSampleTestResultEnum: int
{
    use ArrayableEnum;

    case FIT = 1;
    case UNFIT = 2;
    public function label(): string
    {
        return match ($this) {
            self::FIT => 'Fit',
            self::UNFIT => 'Unfit',
        };
    }
}
