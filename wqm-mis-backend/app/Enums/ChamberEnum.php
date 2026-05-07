<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum ChamberEnum: string
{
    use ArrayableEnum;

    case Satisfactory = 'Satisfactory';
    case Good = 'Good';
    case Worst = 'Worst';
    case NA = 'N/A';

}
