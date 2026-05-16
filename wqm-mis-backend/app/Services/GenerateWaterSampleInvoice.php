<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Setting;
use App\Models\Test;
use App\Models\WaterSamples\WaterSample;

/**
 * F-17 fix.
 *
 * Previously two compounding bugs:
 *   1. The DB row is named `PHE Invoice Discount` but the lookup queried
 *      `phe-invoice-discount` — the configured value was never read, the
 *      hard-coded ?? 50 fallback was always used.
 *   2. The math was inverted: `$sumRate * ($discount / 100)` interpreted
 *      `discount = 50` as "PHE pays 50% of the rate", which is a 50%
 *      DISCOUNT only because 50% off ≡ paying 50%. For any other value the
 *      result was wrong (`discount = 20` charged PHE 20% instead of 80%).
 *
 * Both setting names are now accepted to remain compatible with legacy
 * rows. The math correctly subtracts the discount from full price.
 */
class GenerateWaterSampleInvoice
{
    public const DEFAULT_DISCOUNT_PERCENT = 50;

    public function execute(WaterSample $waterSample)
    {
        $waterSampleTests = $waterSample->waterSampleDetails()
            ->select('test_id')
            ->pluck('test_id');

        $sumRate = (float) Test::query()
            ->whereIn('id', $waterSampleTests)
            ->sum('rate');

        $discount = 0;
        $finalAmount = $sumRate;

        if ($waterSample->collectable_type !== Client::class) {
            // PHE samples receive the configured percentage discount.
            $discount = $this->resolveDiscountPercent();

            // Clamp to the legal range so a misconfigured setting can't
            // produce a negative invoice or a windfall multiplier.
            $discount = max(0, min(100, $discount));

            // F-17 corrected math: amount_payable = full_rate * (1 - discount/100)
            $finalAmount = round($sumRate * (1 - ($discount / 100)), 2);
        }

        return $waterSample
            ->waterSampleInvoice()
            ->create([
                'invoiceable_id'      => $waterSample->collectable_id,
                'invoiceable_type'    => $waterSample->collectable_type,
                'discount_percentage' => $discount,
                'price'               => $finalAmount,
                'net_amount'          => $finalAmount,
                'balance'             => $finalAmount,
                'paid'                => 0,
                'status'              => 'pending',
                'created_by'          => auth()->id(),
            ]);
    }

    /**
     * Look up the configured PHE invoice discount. Accepts both the
     * canonical kebab-case key and the legacy Title-Case key, so existing
     * deployments don't silently fall through to the default.
     */
    public function resolveDiscountPercent(): float
    {
        $row = Setting::query()
            ->whereIn('name', ['phe-invoice-discount', 'PHE Invoice Discount'])
            ->orderByRaw("FIELD(name, 'phe-invoice-discount', 'PHE Invoice Discount')")
            ->first(['name', 'value']);

        if (!$row || $row->value === null || $row->value === '') {
            return (float) self::DEFAULT_DISCOUNT_PERCENT;
        }

        return (float) $row->value;
    }
}
