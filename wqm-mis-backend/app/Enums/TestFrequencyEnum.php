<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum TestFrequencyEnum: string
{
    use ArrayableEnum;

    case FRESH = 'Fresh';
    case RETEST = 'Retest';
    case RETEST_2 = 'Retest 2';
    case RETEST_3 = 'Retest 3';
}
