<?php

namespace Database\Factories;

use App\Models\Tour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tour>
 */
class TourFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Tour ' . $this->faker->unique()->randomNumber(3),
            'price' => $this->faker->randomFloat(2, 1000, 10000),
            'duration' => $this->faker->numberBetween(1, 30),
            'max_group_size' => $this->faker->numberBetween(1, 20),
            'difficulty' => $this->faker->randomElement(['easy', 'medium', 'difficult']),
            'rating' => $this->faker->randomFloat(1, 1, 5),
            'ratings_quantity' => $this->faker->numberBetween(1, 1000),
            'summary' => $this->faker->sentence(10),
            'description' => $this->faker->paragraph(10),
        ];
    }
}
