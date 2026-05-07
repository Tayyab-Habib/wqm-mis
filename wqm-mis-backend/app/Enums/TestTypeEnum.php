<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum TestTypeEnum: string
{
    use ArrayableEnum;

    case PHYSICAL = 'Physical';
    case CHEMICAL = 'Chemical';
    case Microbiological_Medical = 'Microbiological(MF)';
    case Microbiological_Kit = 'Microbiological(Kit)';
    case ON_DEMAND = 'On Demand';
    case HEAVY_METALS = 'Heavy Metals';
    case ORGANIC = 'Organic';
}
