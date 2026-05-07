<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum OperationEnum: string
{
    use ArrayableEnum;

    case Operational = 'Operational';
    case Non_Operational = 'Non-Operational';
    case Work_in_progress = 'Work in progress';
    case Abandoned = 'Abandoned';
}
