<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum IssueTypeEnum: string
{
    use ArrayableEnum;

    case STOCK = 'material';
    case LABORATORY = 'laboratory';
    case INVENTORY = 'asset';
    case COMPLAINT = 'complaint';
}
