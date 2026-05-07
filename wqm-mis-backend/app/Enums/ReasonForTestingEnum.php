<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum ReasonForTestingEnum: string
{
    use ArrayableEnum;

    case GENERAL_Q_ANALYSIS = 'General Q.Analysis';
    case MED_ISSUE = 'Medical Issue';
    case CHEM_ISSUE = 'Chemical Issue';
    case PHY_ISSUE = 'Physical Issue';
    case OTHER = 'Other';
}
