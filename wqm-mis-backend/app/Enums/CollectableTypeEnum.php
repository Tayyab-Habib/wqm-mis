<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum CollectableTypeEnum: string
{
    use ArrayableEnum;

    case PHE = 'PHE';
    case PRIVATE = 'Private';
}
