<?php

namespace Database\Factories;

use App\Enums\IssueTypeEnum;
use App\Models\Asset\Asset;
use App\Models\Complaint;
use App\Models\Laboratories\Laboratory;
use App\Models\Material\Material;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Issues\Issue>
 */
class IssueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        switch (fake()->randomElement(IssueTypeEnum::values())) {
            case IssueTypeEnum::INVENTORY->value:
                $issuableType = Asset::class;
                break;
            case IssueTypeEnum::STOCK->value:
                $issuableType = Material::class;
                break;
            case IssueTypeEnum::COMPLAINT->value:
                $issuableType = Complaint::class;
                break;
            case IssueTypeEnum::LABORATORY->value:
                $issuableType = Laboratory::class;
                break;
        }


        return [
            'user_id' => User::query()->select('id')->inRandomOrder()->first()->id,
            'issuable_id' => 1,
            'issuable_type' => $issuableType,
            'title' => fake()->name,
            'description' => fake()->sentence,
            'file' => '/logos/default-logo.png',
            'status' => 'pending'
        ];
    }
}
