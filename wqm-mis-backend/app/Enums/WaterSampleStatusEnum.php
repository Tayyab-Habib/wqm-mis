<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum WaterSampleStatusEnum: string
{
    use ArrayableEnum;

    case M_R = 'M&R';
    case NEW = 'New';
}
