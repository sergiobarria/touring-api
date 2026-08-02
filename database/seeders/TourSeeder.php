<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Tour;
use App\Models\TourStartDate;
use App\Models\User;
use Illuminate\Database\Seeder;

class TourSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $datesNum = rand(3, 5);
        $leadGuides = User::role(UserRole::LEAD_GUIDE->value)->get();
        $guides = User::role(UserRole::GUIDE->value)->get();

        Tour::factory()->count(20)
            ->state(fn (): array => ['lead_guide_id' => $leadGuides->random()->getKey()])
            ->withImages()
            ->has(TourStartDate::factory()->count($datesNum), 'startDates')
            ->create()
            ->each(function (Tour $tour) use ($guides): void {
                $count = fake()->numberBetween(0, Tour::MAX_SUPPORTING_GUIDES);
                $tour->guides()->sync($count === 0 ? [] : $guides->random($count)->modelKeys());
            });
    }
}
