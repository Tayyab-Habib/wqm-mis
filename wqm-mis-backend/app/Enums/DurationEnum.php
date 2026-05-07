<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum DurationEnum: string
{
    use ArrayableEnum;

    case ANNUAL = 'annual';
    case QUARTER = 'quarter';
    case MONTH = 'month';
}
