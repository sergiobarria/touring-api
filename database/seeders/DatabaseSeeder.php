<?php

namespace Database\Seeders;

use App\Models\StartDate;
use App\Models\Tour;
use App\Models\User;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        Tour::factory()->count(20)->create()->each(function (Tour $tour) {
            $randomAmount = random_int(1, 5);
            
            StartDate::factory()->count($randomAmount)->create([
                'tour_id' => $tour->id,
            ]);
        });
    }
}
