<?php

namespace Database\Factories;

use App\Models\StartDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StartDate>
 */
class StartDateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'start_date' => $this->faker->dateTimeBetween('now', '+1 year'),
        ];
    }
}
