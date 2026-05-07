<?php

namespace Database\Factories;

use App\Enums\IssueTypeEnum;
use App\Models\Asset\Asset;
use App\Models\Material\Material;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvoiceDetail>
 */
class InvoiceDetailFactory extends Factory
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
                $invoiceableType = Asset::class;
                break;
            case IssueTypeEnum::STOCK->value:
                $invoiceableType = Material::class;
                break;
        }

        return [
            'invoice_id' => 1,
            'invoiceable_id' => 1,
            'invoiceable_type' => $invoiceableType,
            'name' => fake()->name,
            'quantity' => fake()->numberBetween(1, 20),
            'unit' => fake()->randomElement(['kg','mL','L','g']),
            'price' => fake()->numberBetween(10000,100000),
        ];
    }
}
