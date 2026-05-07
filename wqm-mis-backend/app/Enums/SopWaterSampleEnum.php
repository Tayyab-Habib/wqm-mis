<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum SopWaterSampleEnum: string
{
    use ArrayableEnum;

    case SOP_COLLECTION = 'sop-collection';
    case SOP_ANALYSIS = 'sop-analysis';
}
