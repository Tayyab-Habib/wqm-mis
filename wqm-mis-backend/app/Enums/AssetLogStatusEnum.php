<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum AssetLogStatusEnum: string
{
    use ArrayableEnum;

    case IN = 'in';
    case OUT = 'out';
}
