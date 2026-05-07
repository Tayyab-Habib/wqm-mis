<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum NotificationSlaStatusEnum: int
{
    use ArrayableEnum;

    case PENDING = 1;
    case ACTION_TAKEN = 2;
    case DELAYED = 3;
}
