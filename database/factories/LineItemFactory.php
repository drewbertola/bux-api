<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LineItem>
 */
class LineItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoiceId' => Invoice::factory(),
            'price' => fake()->randomFloat(2, 5, 200),
            'units' => 'ea',
            'quantity' => fake()->randomFloat(2, 1, 10),
            'description' => fake()->words(3, true),
        ];
    }
}
