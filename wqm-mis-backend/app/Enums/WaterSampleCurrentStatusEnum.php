<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum WaterSampleCurrentStatusEnum: int
{
    use ArrayableEnum;

    case PENDING = 1;
    case FIT = 2;
    case UNFIT = 3;
    case IN_PROGRESS = 4;
    case CLOSED = 5;
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::FIT => 'Fit',
            self::UNFIT => 'Unfit',
            self::IN_PROGRESS => 'In Progress',
            self::CLOSED => 'Closed',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING => 'bg-secondary',
            self::FIT => 'bg-success',
            self::UNFIT => 'bg-danger',
            self::IN_PROGRESS => 'bg-warning',
            self::CLOSED => 'bg-dark',
        };
    }
}
