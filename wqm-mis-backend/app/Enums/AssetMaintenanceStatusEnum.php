<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum AssetMaintenanceStatusEnum: string
{
    use ArrayableEnum;

    case UNDER_SERVICE = 'under_service';
    case DELAYED = 'delayed';
    case SERVICED = 'serviced';
    case BROKEN = 'broken';
}
