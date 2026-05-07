<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum ClientTypeEnum: string
{
    use ArrayableEnum;

    case ORGANIZATION = 'organization';
    case INDIVIDUAL = 'individual';
}
