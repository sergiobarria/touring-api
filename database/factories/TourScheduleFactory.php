<?php

namespace Database\Factories;

use App\Models\Tour;
use App\Models\TourSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

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
            'start_datetime_utc' => Carbon::now('UTC')
                ->addDays(fake()->numberBetween(1, 180))
                ->addHours(fake()->numberBetween(8, 18))
                ->addMinutes(fake()->randomElement([0, 30])),
            'available_spots' => fake()->numberBetween(0, 15),
            'is_active' => fake()->boolean(90)
        ];
    }
}
