<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum CollectedByEnum: string
{
    use ArrayableEnum;

    case CLIENT = 'Client';
    case LAB_STAFF = 'Laboratory Staff';
}
