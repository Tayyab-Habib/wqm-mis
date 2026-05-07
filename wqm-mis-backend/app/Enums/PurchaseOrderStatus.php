<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum PurchaseOrderStatus: string
{
    use ArrayableEnum;

    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case REQUESTED = 'requested';
    case FULL_FILLED = 'full_filled';
    case CANCELED = 'canceled';
}
