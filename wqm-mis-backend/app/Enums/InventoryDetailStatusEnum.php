<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum InventoryDetailStatusEnum: string
{
    use ArrayableEnum;

    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case ISSUED = 'issued';
}
