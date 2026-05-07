<?php

namespace Database\Factories;

use App\Models\District;
use App\Models\Division;
use App\Models\Province;
use App\Models\Tehsil;
use App\Models\UnionCouncil;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WaterScheme>
 */
class WaterSchemeFactory extends Factory
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
            'latitude' => fake()->randomFloat(-90, 90),
            'longitude' => fake()->randomFloat(-180, 180),
            'slug' => fake()->name,
            'address' => fake()->sentence,
            'union_council_id' => UnionCouncil::query()->select('id')->inRandomOrder()->first()?->id,
            'tehsil_id' => Tehsil::query()->select('id')->inRandomOrder()->first()->id,
            'district_id' => District::query()->select('id')->inRandomOrder()->first()->id,
            'division_id' => Division::query()->select('id')->inRandomOrder()->first()->id,
            'province_id' => Province::query()->select('id')->inRandomOrder()->first()->id,
        ];
    }
}
