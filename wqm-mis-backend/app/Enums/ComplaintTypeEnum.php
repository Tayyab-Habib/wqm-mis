<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum ComplaintTypeEnum: string
{
    use ArrayableEnum;

    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case CLOSED = 'closed';
    case RE_OPENED = 're_opened';
}
