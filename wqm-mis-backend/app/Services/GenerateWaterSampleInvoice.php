<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Setting;
use App\Models\Test;
use App\Models\WaterSamples\WaterSample;
use App\Models\WaterSamples\WaterSampleInvoice;
use Illuminate\Database\Eloquent\Collection;

class GenerateWaterSampleInvoice
{
    /**
     * Generate invoice for newly registered water sample.
     *
     * @param WaterSample $waterSample
     */
    public function execute(WaterSample $waterSample)
    {
        $waterSampleTests = $waterSample->waterSampleDetails()
            ->select('test_id')
            ->pluck('test_id');


        $sumRate = Test::query()
            ->whereIn('id', $waterSampleTests)
            ->sum('rate');

        $discount = 0;

        if ($waterSample->collectable_type !== Client::class) {
            $discount = Setting::query()
                ->select('value')
                ->where('name', '=','phe-invoice-discount')
                ->first()?->value ?? 50;
            $sumRate = $sumRate * ($discount / 100);
        }

        return $waterSample
            ->waterSampleInvoice()
            ->create([
                'invoiceable_id' => $waterSample->collectable_id,
                'invoiceable_type' => $waterSample->collectable_type,
                'discount_percentage' => $discount,
                'price' => $sumRate,
                'balance' => $sumRate,
                'created_by' => auth()->id()
            ]);
    }
}
