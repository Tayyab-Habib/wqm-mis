<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum MaterialStatusEnum: string
{
    use ArrayableEnum;

    case ACTIVE = 'active';
    case BELOW_THRESHOLD = 'below_threshold';
    case DEPLETED = 'depleted';
    case EXPIRED = 'expired';
}
