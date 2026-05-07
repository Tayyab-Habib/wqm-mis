<?php

namespace Database\Factories\Material;

use App\Enums\MaterialLogStatusEnum;
use App\Enums\MaterialStatusEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Material\MaterialLog>
 */
class MaterialLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $status = fake()->randomElement(MaterialLogStatusEnum::values());
        $quantity = fake()->numberBetween(3, 100);

        return [
            'user_id' => User::query()->select('id')->inRandomOrder()->first()->id,
            'date_of_expiry' => now()->addMonth()->format('Y-m-d'),
            'quantity' => $status !== MaterialLogStatusEnum::OUT->value ? $quantity : ($quantity - ($quantity * 2)),
            'unit' => fake()->word,
            'date_of_entry' => now(),
            'status' => $status,
        ];
    }
}
