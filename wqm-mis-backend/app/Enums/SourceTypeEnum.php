<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum SourceTypeEnum: string
{
    use ArrayableEnum;

    case PUMPING = 'Pumping';
    case GRAVITY = 'Gravity';
}
