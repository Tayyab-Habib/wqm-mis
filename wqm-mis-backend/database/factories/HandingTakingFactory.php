<?php

namespace Database\Factories;

use App\Enums\IssueTypeEnum;
use App\Models\Asset\Asset;
use App\Models\Material\Material;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HandingTaking>
 */
class HandingTakingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {

        switch (fake()->randomElement([IssueTypeEnum::STOCK->value, IssueTypeEnum::INVENTORY->value])) {
            case IssueTypeEnum::INVENTORY->value:
                $stockableType = Asset::class;
                break;
            case IssueTypeEnum::STOCK->value:
                $stockableType = Material::class;
                break;
        }

        $userId = User::query()->select('id')->inRandomOrder()->first()->id;

        return [
            'stockable_id' => 1,
            'stockable_type' => $stockableType,
            'description' => fake()->sentence,
            'quantity' => fake()->numberBetween(1, 20),
            'unit' => fake()->randomElement(['kg', 'mL', 'L', 'g']),
            'assigned_to' => $userId,
            'laboratory_id' => 1,
        ];
    }
}
