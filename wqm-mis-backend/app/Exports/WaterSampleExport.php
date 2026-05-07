<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class WaterSampleExport implements FromArray
{
    protected $waterSamples;

    public function __construct($waterSamples)
    {
        $this->waterSamples = $waterSamples;
    }

    public function array(): array
    {
        if ($this->waterSamples->isEmpty()) {
            return [];
        }

        $rows = [];

        foreach ($this->waterSamples as $waterSample) {
            $rows[] = [
                'id' => $waterSample->id,
                'slug' => $waterSample->slug,
                'district_id' => $waterSample->district_id,
                'latitude' => $waterSample->latitude,
                'longitude' => $waterSample->longitude,
                'result' => $waterSample->result,
            ];
        }

        return $rows;
    }
}
