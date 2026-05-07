<?php

namespace Database\Factories\WaterSamples;

use App\Enums\CollectedByEnum;
use App\Enums\CollectedInEnum;
use App\Enums\DesiredTestEnum;
use App\Enums\ReasonForTestingEnum;
use App\Enums\SamplingPointEnum;
use App\Enums\SourceTypeEnum;
use App\Enums\TestFrequencyEnum;
use App\Enums\WaterSampleResultEnum;
use App\Enums\WaterSampleStatusEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WaterSamples\WaterSample>
 */
class WaterSampleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'test_type' => fake()->randomElement(TestFrequencyEnum::values()),
            'slug' => fake()->name,
            'water_scheme_id' => 1,
            'source_type' => fake()->randomElement(SourceTypeEnum::values()),
            'sampling_point' => fake()->randomElement(SamplingPointEnum::values()),
            'collected_by' => fake()->randomElement(CollectedByEnum::values()),
            'latitude' => fake()->randomFloat(-90, 90),
            'longitude' => fake()->randomFloat(-180, 180),
            'status' => fake()->randomElement(WaterSampleStatusEnum::values()),
            'temperature_in_celsius' => fake()->numberBetween(32, 40),
            'sampled_at' => fake()->dateTime,
            'analyzed_at' => fake()->dateTime,
            'collected_in' => fake()->randomElement(CollectedInEnum::values()),
            'complaint' => fake()->randomElement(ReasonForTestingEnum::values()),
            'desired_test' => fake()->randomElement(DesiredTestEnum::values()),
            'result' => fake()->randomElement(WaterSampleResultEnum::values()),
            'laboratory_id' => 1,
            'union_council_id' => 1,
            'tehsil_id' => 1,
            'district_id' => 1,
            'division_id' => 1,
            'province_id' => 1,
            'collectable_id' => 1,
            'collectable_type' => User::class,
            'lab_incharge_id' => 1,
            'research_officer_id' => 1,
        ];
    }
}
