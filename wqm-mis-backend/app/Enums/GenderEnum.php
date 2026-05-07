<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum GenderEnum: string
{
    use ArrayableEnum;

    case Male = 'male';
    case Female = 'female';
    case Other = 'other';
}
