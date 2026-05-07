<?php

namespace Database\Factories;

use App\Enums\DiaryDispatchEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DiaryDispatch>
 */
class DiaryDispatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'subject' => fake()->sentence,
            'person_name' => fake()->name,
            'date_on_letter' => fake()->date(),
            'receival_date' => fake()->date(),
            'attachment_name' => fake()->sentence,
            'attachment' => fake()->sentence,
            'type' => fake()->randomElement(DiaryDispatchEnum::values()),
            'designation_id' => 1,
            'folder_id' => 1,
        ];
    }
}
