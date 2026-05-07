<?php

namespace Database\Seeders;

use App\Models\WaterSamples\WaterSampleDetail;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WaterSampleDetailSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $realWaterSampleDetail = [
            [
                'water_sample_id' => 1,
                'test_id' => 1,
                'analysis_result' => 'Cloudy',
            ],
            [
                'water_sample_id' => 1,
                'test_id' => 2,
                'analysis_result' => 'U.O',
            ],
            [
                'water_sample_id' => 1,
                'test_id' => 3,
                'analysis_result' => 'U.O',
            ],
            [
                'water_sample_id' => 1,
                'test_id' => 4,
                'analysis_result' => '8.3',
            ],
            [
                'water_sample_id' => 1,
                'test_id' => 5,
                'analysis_result' => '295',
            ],
            [
                'water_sample_id' => 1,
                'test_id' => 6,
                'analysis_result' => '18.62',
            ],
            [
                'water_sample_id' => 1,
                'test_id' => 7,
                'analysis_result' => '177.0',
            ],
            [
                'water_sample_id' => 1,
                'test_id' => 8,
                'analysis_result' => '200.0',
            ],
            [
                'water_sample_id' => 1,
                'test_id' => 9,
                'analysis_result' => '35.2',
            ],
            [
                'water_sample_id' => 1,
                'test_id' => 10,
                'analysis_result' => '27.2',
            ],
            [
                'water_sample_id' => 1,
                'test_id' => 11,
                'analysis_result' => '100.0',
            ],
            [
                'water_sample_id' => 1,
                'test_id' => 12,
                'analysis_result' => '0.0',
            ],
            [
                'water_sample_id' => 1,
                'test_id' => 13,
                'analysis_result' => '100.0',
            ],
            [
                'water_sample_id' => 1,
                'test_id' => 14,
                'analysis_result' => '36.4',
            ],
            [
                'water_sample_id' => 1,
                'test_id' => 15,
                'analysis_result' => 'NT',
            ],
            [
                'water_sample_id' => 1,
                'test_id' => 16,
                'analysis_result' => 'BDL',
            ],
            [
                'water_sample_id' => 1,
                'test_id' => 17,
                'analysis_result' => 'BDL',
            ],
            [
                'water_sample_id' => 1,
                'test_id' => 18,
                'analysis_result' => 'NT',
            ],
            [
                'water_sample_id' => 1,
                'test_id' => 19,
                'analysis_result' => 'NT',
            ],
            [
                'water_sample_id' => 1,
                'test_id' => 20,
                'analysis_result' => '+Ve',
            ]
        ];

        try {
            DB::beginTransaction();
            WaterSampleDetail::query()->insert($realWaterSampleDetail);
            DB::commit();
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            DB::rollBack();
        }
    }
}
