<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum InventoryStatusEnum: string
{
    use ArrayableEnum;

    case PENDING = 'pending';
    case APPROVED = 'approved';
    case PARTIALLY_APPROVED = 'partially_approved';
    case REJECTED = 'rejected';

    case PARTIALLY_ISSUED = 'partially_issued';
    case ISSUED = 'issued';
}
