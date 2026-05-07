<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum DesiredTestEnum: string
{
    use ArrayableEnum;

    case Physical = 'Physical';
    case Chemical = 'Physical & Chemical';
    case Microbiological_Medical = 'Microbiological(MF)';
    case Microbiological_Kit = 'Microbiological(Kit)';
    case On_Demand = 'On Demand';

}
