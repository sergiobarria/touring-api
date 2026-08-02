<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Tour;
use App\Models\TourStartDate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Booking> */
final class BookingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tour_id' => Tour::factory(),
            'tour_start_date_id' => fn (array $attributes) => TourStartDate::factory()->create(['tour_id' => $attributes['tour_id']])->id,
            'reference' => 'BK-'.Str::upper(Str::random(12)),
            'status' => BookingStatus::CONFIRMED,
            'ticket_quantity' => 1,
            'unit_amount' => 10000,
            'total_amount' => 10000,
            'currency' => 'usd',
            'tour_name' => fake()->sentence(3),
            'departure_datetime_utc' => now('UTC')->addMonth(),
            'purchaser_name' => fake()->name(),
            'purchaser_email' => fake()->safeEmail(),
            'discount_percent' => null,
            'idempotency_key_hash' => hash('sha256', Str::uuid()->toString()),
            'request_hash' => hash('sha256', Str::random()),
            'paid_at' => now('UTC'),
        ];
    }
}
