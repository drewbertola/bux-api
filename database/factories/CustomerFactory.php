<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'bAddress1' => fake()->streetAddress(),
            'bCity' => fake()->city(),
            'bState' => fake()->stateAbbr(),
            'bZip' => fake()->postcode(),
            'sAddress1' => fake()->streetAddress(),
            'sCity' => fake()->city(),
            'sState' => fake()->stateAbbr(),
            'sZip' => fake()->postcode(),
            'phoneMain' => fake()->phoneNumber(),
            'primaryContact' => fake()->name(),
            'primaryEmail' => fake()->safeEmail(),
            'primaryPhone' => fake()->phoneNumber(),
            'archive' => 'N',
        ];
    }
}
