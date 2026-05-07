<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum AssetStatusEnum: string
{
    use ArrayableEnum;

    case ACTIVE = 'Active';
//    case BELOW_THRESHOLD = 'below_threshold';
    case UNDER_SERVICE = 'Under_service';
    case BROKEN = 'Broken';
    case INACTIVE = 'InActive';
    case PARTIAL = 'Partial';

}
