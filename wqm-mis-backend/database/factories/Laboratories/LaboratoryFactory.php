<?php

namespace Database\Factories\Laboratories;

use App\Models\Laboratory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Laboratory>
 */
class LaboratoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => fake()->name(),
            'latitude' => fake()->randomFloat(-90, 90),
            'longitude' => fake()->randomFloat(-180, 180),
            'phone' => fake()->phoneNumber,
            'fax' => rand(99, 999) . '-' . rand(99, 999) . '-' . rand(999, 9999),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->paragraph,
            'focal_person_id' => 1,
            'logo' => '/logos/default-logo.png',
            'tehsil_id' => 1,
            'district_id' => 1,
            'division_id' => 1,
            'province_id' => 1,
        ];
    }
}
