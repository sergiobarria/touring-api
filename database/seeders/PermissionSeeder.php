<?php

namespace Database\Seeders;

use App\Enums\UserPermission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

final class PermissionSeeder extends Seeder
{
    private const string GUARD_NAME = 'web';

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (UserPermission::cases() as $permission) {
            Permission::findOrCreate($permission->value, self::GUARD_NAME);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
