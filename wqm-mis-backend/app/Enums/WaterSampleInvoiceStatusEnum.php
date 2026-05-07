<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum WaterSampleInvoiceStatusEnum: string
{
    use ArrayableEnum;

    case PENDING = 'pending';
    case PARTIAL = 'partial';
    case PAID = 'paid';
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PARTIAL => 'Partial',
            self::PAID => 'Paid',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING => 'bg-secondary',
            self::PARTIAL => 'bg-warning',
            self::PAID => 'bg-success',
        };
    }
}
