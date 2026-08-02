<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Review;
use App\Models\Tour;
use App\Models\User;
use App\Services\Tours\TourRatingService;
use Illuminate\Database\Seeder;

final class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::role(UserRole::USER->value)->get();
        $ratings = app(TourRatingService::class);

        Tour::query()->each(function (Tour $tour) use ($users, $ratings): void {
            $reviewers = $users->random(fake()->numberBetween(3, min(10, $users->count())));

            foreach ($reviewers as $user) {
                Review::factory()->for($tour)->for($user)->create();
            }

            $ratings->recompute($tour);
        });
    }
}
