<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

final class UserSeeder extends Seeder
{
    public const int USER_COUNT = 25;

    public function run(): void
    {
        User::factory()->count(self::USER_COUNT)->withRole(UserRole::USER)->create();
    }
}
