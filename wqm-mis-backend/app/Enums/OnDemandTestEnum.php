<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum OnDemandTestEnum: string
{
    use ArrayableEnum;

    case ARSENIC = 'Arsenic';
    case FLUORIDE = 'Fluoride';
    case IRON = 'Iron';
    case MERCURY = 'Mercury';
    case ALUMINUM = 'Aluminum';
    case AMMONIA = 'Ammonia';
    case CHROMIUM = 'Chromium';
    case COPPER = 'Copper';
    case CYANIDE = 'Cyanide';
    case IODINE = 'Iodine';
    case MANGANESE = 'Manganese';
    case MOLYBDENUM = 'Molybdenum';
    case NICKLE = 'Nickle';
    case PHOSPHORUS = 'Phosphorus';
    case TOTAL_CHLORINE = 'Total Chlorine';
    case FREE_CHLORINE = 'Free Chlorine';
}
