<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

/**
 * Backing values stay lowercase (`pending|partial|paid`) for backwards
 * compatibility with all existing rows. The human-readable label() reflects
 * the exact terminology required by SRS §2.18.2.
 */
enum WaterSampleInvoiceStatusEnum: string
{
    use ArrayableEnum;

    case PENDING = 'pending';
    case PARTIAL = 'partial';
    case PAID    = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Unpaid',         // SRS §2.18.2 — default on generation
            self::PARTIAL => 'Partially Paid', // SRS §2.18.2 — exact wording
            self::PAID    => 'Paid',           // SRS §2.18.2
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING => 'bg-secondary',
            self::PARTIAL => 'bg-warning',
            self::PAID    => 'bg-success',
        };
    }
}
