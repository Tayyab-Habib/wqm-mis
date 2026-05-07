<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum PowerInputEnum: string
{
    use ArrayableEnum;

    case WAPDA = 'Wapda';
    case SOLAR = 'Solar';
}
