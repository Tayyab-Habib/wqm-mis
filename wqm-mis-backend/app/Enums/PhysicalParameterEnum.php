<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum PhysicalParameterEnum: string
{
    use ArrayableEnum;

    case NT = 'NT';
    case UN_OBJECTIONABLE = 'Un-objectionable';
    case ACCEPTABLE = 'Acceptable';
    case OBJECTIONABLE = 'Objectionable';
}
