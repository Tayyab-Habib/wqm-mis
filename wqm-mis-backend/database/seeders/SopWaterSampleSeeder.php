<?php

namespace Database\Seeders;

use App\Models\SopWaterSample;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SopWaterSampleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if (0 === SopWaterSample::query()->count()) {
            SopWaterSample::factory(10)->create();
        }
    }
}
