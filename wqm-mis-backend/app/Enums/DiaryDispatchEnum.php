<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum DiaryDispatchEnum: string
{
    use ArrayableEnum;

    case DIARY = 'diary';
    case DISPATCH = 'dispatch';
}
