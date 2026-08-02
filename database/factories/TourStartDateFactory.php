<?php

namespace Database\Factories;

use App\Models\TourStartDate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TourStartDate>
 */
class TourStartDateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'start_datetime_utc' => Carbon::now('UTC')
                ->addDays(fake()->numberBetween(1, 180))
                ->addHours(fake()->numberBetween(8, 18))
                ->addMinutes(fake()->randomElement([0, 30])),
            'available_spots' => fake()->numberBetween(0, 30),
            'is_active' => fake()->boolean(90),
        ];
    }
}
