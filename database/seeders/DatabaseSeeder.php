<?php

namespace Database\Seeders;

use App\Models\Tour;
use App\Models\TourSchedule;
use Illuminate\Database\Seeder;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->environment() == 'local') {
            Tour::factory()->count(20)
                ->has(TourSchedule::factory()->count(3), 'schedules')
                ->create();
        }
    }
}
