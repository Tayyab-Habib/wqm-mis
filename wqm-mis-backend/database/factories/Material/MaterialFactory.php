<?php

namespace Database\Factories\Material;

use App\Enums\MaterialStatusEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Material\Material>
 */
class MaterialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => fake()->word,
            'quantity' => fake()->numberBetween(3, 100),
            'available_quantity' => fake()->numberBetween(3, 100),
            'unit' => fake()->word,
            'threshold' => fake()->numberBetween(3,10),
            'status' => fake()->randomElement(MaterialStatusEnum::values()),
        ];
    }
}
