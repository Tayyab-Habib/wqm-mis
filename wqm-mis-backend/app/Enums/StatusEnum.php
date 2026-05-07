<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum StatusEnum: string
{
    use ArrayableEnum;

    case ACTIVE = 'active';
    case IN_ACTIVE = 'inactive';
}
