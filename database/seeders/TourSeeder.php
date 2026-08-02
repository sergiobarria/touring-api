<?php

namespace Database\Seeders;

use App\Models\Tour;
use App\Models\TourStartDate;
use Illuminate\Database\Seeder;

class TourSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $datesNum = rand(3, 5);

        Tour::factory()
            ->count(20)
            ->withImages()
            ->has(TourStartDate::factory()->count($datesNum), 'startDates')
            ->create();
    }
}
