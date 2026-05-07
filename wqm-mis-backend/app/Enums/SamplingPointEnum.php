<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum SamplingPointEnum: string
{
    use ArrayableEnum;

    case SOURCE = 'Source';
    case CEND = 'Consumer End';
    case MID = 'Mid';
}
