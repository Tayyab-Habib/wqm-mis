<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum AssetMaintenanceTypeEnum: string
{
    use ArrayableEnum;

    case Calibration = 'Calibration';
    case Service = 'Service';
}
