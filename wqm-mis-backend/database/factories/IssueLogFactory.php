<?php

namespace Database\Factories;

use App\Models\Issues\Issue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Issues\IssueLog>
 */
class IssueLogFactory extends Factory
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
            'issue_id' => Issue::query()->select('id')->inRandomOrder()->first()->id,
            'comment' => fake()->sentence,
            'file' => '/logos/default-logo.png',
//            'status' => fake()->randomElement(IssueStatusEnum::values())
            'status' => 'pending'
        ];
    }
}
