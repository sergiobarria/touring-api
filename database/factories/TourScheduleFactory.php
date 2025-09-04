<?php

namespace Database\Factories;

use App\Models\Tour;
use App\Models\TourSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TourSchedule>
 */
class TourScheduleFactory extends Factory
{
    protected $model = TourSchedule::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tour_id' => Tour::factory(),
            'start_datetime_utc' => fake()->dateTimeBetween('now', '+8 months'),
            'available_spots' => fake()->numberBetween(0, 15),
            'is_active' => fake()->boolean(90)
        ];
    }
}
