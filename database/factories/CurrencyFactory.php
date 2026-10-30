<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CurrencyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->currencyCode(),
            'symbol' => '$',
            'exchange_rate' => fake()->randomFloat(6, 0.5, 5000),
        ];
    }
}
