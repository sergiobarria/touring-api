<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Call seeders needed in all environments
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);

        // Call seeders needed only in development
        if (app()->environment('local')) {
            $this->call([
                TourSeeder::class,
                // other development seeders...
            ]);
        }
    }
}
