<?php

namespace Database\Factories;

use App\Models\Tour;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tour>
 */
class TourFactory extends Factory
{
    protected $model = Tour::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement([
            'The Forest Hiker',
            'The Snow Adventurer',
            'The City Wanderer',
            'The Park Camper',
            'The Sports Lover',
            'The Wine Taster',
            'The Star Gazer',
            'The Northern Lights',
            'The Forest Explorer',
            'Mountain Expedition',
            'Safari Adventure',
            'Historical Journey',
            'Island Paradise',
            'Cultural Exploration',
            'Wildlife Safari',
            'Alpine Adventure',
            'Desert Expedition',
            'Mystic Forest Trek'
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'duration_days' => fake()->numberBetween(1, 30),
            'max_group_size' => fake()->numberBetween(4, 20),
            'difficulty' => fake()->randomElement(['easy', 'moderate', 'difficult']),
            'price' => fake()->randomFloat(2, 299, 2999),
            'price_discount_percent' => fake()->optional(0.3)->randomFloat(2, 5, 25),
            'rating_avg' => fake()->optional(0.8)->randomFloat(2, 3.5, 5),
            'rating_count' => fake()->optional(0.8)->numberBetween(12, 247),
            'summary' => fake()->sentence(12),
            'description' => fake()->optional(0.9)->paragraph(3, true)
        ];
    }
}
