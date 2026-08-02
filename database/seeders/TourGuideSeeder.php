<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Users\UserRoleService;
use Illuminate\Database\Seeder;

class TourGuideSeeder extends Seeder
{
    public const int LEAD_GUIDE_COUNT = 10;

    public const int GUIDE_COUNT = 25;

    public function run(): void
    {
        $roles = app(UserRoleService::class);

        foreach (range(1, self::LEAD_GUIDE_COUNT) as $number) {
            $user = User::query()->firstOrCreate([
                'email' => "lead-guide-{$number}@example.test",
            ], [
                'name' => "Lead Guide {$number}",
                'email_verified_at' => now(),
                'password' => 'password',
            ]);
            $roles->assign($user, UserRole::LEAD_GUIDE);
        }

        foreach (range(1, self::GUIDE_COUNT) as $number) {
            $user = User::query()->firstOrCreate([
                'email' => "tour-guide-{$number}@example.test",
            ], [
                'name' => "Tour Guide {$number}",
                'email_verified_at' => now(),
                'password' => 'password',
            ]);
            $roles->assign($user, UserRole::GUIDE);
        }
    }
}
