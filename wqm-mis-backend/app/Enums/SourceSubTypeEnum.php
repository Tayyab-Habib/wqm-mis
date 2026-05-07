<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum SourceSubTypeEnum: string
{
    use ArrayableEnum;

    case DAM = 'Dam';
    case RESERVOIR = 'Reservoir';
    case TUBE_WELL = 'Tube Well';
    case HAND_PUMP = 'Hand Pump';
    case PRESS_PUMP = 'Press: Pump';
}
