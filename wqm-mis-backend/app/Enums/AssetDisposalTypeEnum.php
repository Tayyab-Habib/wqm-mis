<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum AssetDisposalTypeEnum: string
{
    use ArrayableEnum;

    case CONDEMNED    = 'condemned';
    case MISSING_LOST = 'missing_lost';
    case TRANSFERRED  = 'transferred';
    case DISPOSED     = 'disposed';
    case DONATED      = 'donated';
}
