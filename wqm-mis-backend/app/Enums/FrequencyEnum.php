<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum FrequencyEnum: string
{
    use ArrayableEnum;

    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case YEARLY = 'yearly';
}
