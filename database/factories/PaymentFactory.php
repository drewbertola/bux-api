<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customerId' => Customer::factory(),
            'invoiceId' => null,
            'amount' => fake()->randomFloat(2, 10, 1000),
            'date' => fake()->date(),
            'method' => fake()->randomElement(['Cash', 'Check', 'Card', 'Transfer']),
            'number' => '',
        ];
    }
}
