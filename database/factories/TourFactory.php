<?php

namespace Database\Factories;

use App\Enums\TourDifficulty;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
        $name = fake()->unique()->bothify('Tour ???-###');
        $hasRatings = fake()->boolean(80);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'duration_days' => fake()->numberBetween(1, 30),
            'max_group_size' => fake()->numberBetween(4, 20),
            'difficulty' => fake()->randomElement(TourDifficulty::cases()),
            'price' => fake()->randomFloat(2, 299, 2999),
            'price_discount_percent' => fake()
                ->optional(0.3)
                ->randomElement([5, 10, 15, 20, 25]),
            'rating_avg' => $hasRatings
                ? fake()->randomFloat(2, 3.5, 5)
                : null,
            'rating_count' => $hasRatings
                ? fake()->numberBetween(1, 247)
                : 0,
            'summary' => fake()->sentence(12),
            'description' => fake()->optional(0.9)->paragraph(3, true),
        ];
    }
}
