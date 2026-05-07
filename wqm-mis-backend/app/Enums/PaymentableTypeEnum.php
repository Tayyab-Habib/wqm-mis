<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum PaymentableTypeEnum: string
{
    use ArrayableEnum;

    case WATER_SAMPLE = 'water_sample';
}
