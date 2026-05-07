<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum InvoiceableTypeEnum: string
{
    use ArrayableEnum;
    case STOCK = 'material';
    case INVENTORY = 'asset';
}
