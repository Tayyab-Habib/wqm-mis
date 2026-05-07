<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum WaterSampleTestStatusEnum: int
{
    use ArrayableEnum;

    case PENDING = 0;
    case COMPLETED = 1;
    case IN_PROGRESS = 2;
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::COMPLETED => 'Completed',
            self::IN_PROGRESS => 'In Progress',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING => 'bg-secondary',
            self::COMPLETED => 'bg-success',
            self::IN_PROGRESS => 'bg-warning',
        };
    }
}
