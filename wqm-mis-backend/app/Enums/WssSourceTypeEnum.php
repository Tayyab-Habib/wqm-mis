<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum WssSourceTypeEnum: string
{
    use ArrayableEnum;

    case Spring = 'Spring';
    case Sump_Well = 'Sump well';
    case Pumping = 'Pumping';
    case Gravity = 'Gravity';
    case Speed_Well = 'Speedwell';
    case Surface_Water = 'Surface Water';
    case Cutoff_Wall = 'Cutoff Wall';
    case Infiltration_Gallery = 'Infiltration Gallery';
	case Collecting_Well = 'Collecting Well';
	case Dug_Well = 'Dug well';
	case Tube_Well = 'Tube Well';
}
