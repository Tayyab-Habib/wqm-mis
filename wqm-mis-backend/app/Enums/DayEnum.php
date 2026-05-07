<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum DayEnum: string
{
    use ArrayableEnum;

    case MONDAY = 'monday';
    case TUESDAY = 'tuesday';
    case WEDNESDAY = 'wednesday';
    case THURSDAY = 'thursday';
    case FRIDAY = 'friday';
    case SATURDAY = 'saturday';
    case SUNDAY = 'sunday';
    case OUT = 'out';
}
