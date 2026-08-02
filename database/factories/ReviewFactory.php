<?php

namespace Database\Factories;

use App\Models\Review;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Review> */
class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tour_id' => Tour::factory(),
            'user_id' => User::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'review' => fake()->paragraphs(fake()->numberBetween(1, 3), true),
        ];
    }
}
