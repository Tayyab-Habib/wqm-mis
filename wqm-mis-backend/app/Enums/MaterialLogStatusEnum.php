<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum MaterialLogStatusEnum: string
{
    use ArrayableEnum;

    case IN = 'in';
    case OUT = 'out';
    case WASTE = 'waste';
}
