<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum EmploymentStatusEnum: string
{
    use ArrayableEnum;

    case PERMANENT = 'permanent';
    case CONTRACTUAL = 'contractual';
    case OTHER = 'other';
}
