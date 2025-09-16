<?php

namespace Database\Seeders;

use App\Models\Tour;
use App\Models\TourDate;
use Illuminate\Database\Seeder;

class TourSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Tour::factory(20)->create()->each(function (Tour $tour) {
            $tour->dates()->saveMany(
                TourDate::factory()->count(rand(3, 5))->make()
            );
        });
    }
}
