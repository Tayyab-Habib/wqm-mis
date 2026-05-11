<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum StockOutTypeEnum: string
{
    use ArrayableEnum;

    case ANALYSIS           = 'analysis';
    case WRITE_OFF          = 'write_off';
    case TRANSFER           = 'transfer';
    case CALIBRATION        = 'calibration';
    case INTER_LAB_ISSUANCE = 'inter_lab_issuance';
}
