<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum CollectedInEnum: string
{
    use ArrayableEnum;

    case PLASTIC_BOTTLE = 'Plastic Bottle';
    case BAVERAGE_BOTTLE = 'Baverage Bottle';
    case PLASTIC_BOTTLE_KIT = 'Plastic Bottle+Kit';
    case BAVERAGE_BOTTLE_KIT = 'Baverage Bottle+Kit';
    case GLASS_BOTTLE = 'Glass Bottle';
    case KIT = 'Kit';
    case SYRINGE = 'Syringe';
    case OTHER = 'Other';
}
