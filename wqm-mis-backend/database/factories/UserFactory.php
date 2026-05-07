<?php

namespace Database\Factories;

use App\Enums\EmploymentStatusEnum;
use App\Enums\GenderEnum;
use App\Models\Designation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
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
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'remember_token' => Str::random(10),
            'phone' => fake()->phoneNumber,
            'gender' => fake()->randomElement(GenderEnum::values()),
            'date_of_birth' => fake()->date,
            'date_of_joining' => fake()->date,
            'employee_status' => fake()->randomElement(EmploymentStatusEnum::values()),
            'career_background' => fake()->paragraph(),
            'educational_background' => fake()->paragraph(),
            'basic_pay_scale' => fake()->randomElement(range(1, 22)),
            'designation_id' => Designation::query()->select('id')->inRandomOrder()->first()->id,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return static
     */
    public function unverified()
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
