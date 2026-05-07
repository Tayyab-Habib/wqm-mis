<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum ResponsibleTypeEnum: string
{
    use ArrayableEnum;

    case PRIMARY = 'primary';
    case SECONDARY = 'secondary';
}
