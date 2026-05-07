<?php

namespace Database\Factories;

use App\Enums\SopWaterSampleEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SopWaterSample>
 */
class SopWaterSampleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'user_id' => User::query()->select('id')->inRandomOrder()->first()->id,
            'type' => fake()->randomElement(SopWaterSampleEnum::values()),
            'description' => fake()->text(),
        ];
    }
}
