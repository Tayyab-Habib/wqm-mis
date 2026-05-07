<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum WaterSampleResultEnum: string
{
    use ArrayableEnum;

    case FIT = 'Fit';
    case UNFIT = 'Unfit';
}
