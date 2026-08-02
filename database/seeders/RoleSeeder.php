<?php

namespace Database\Seeders;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Users\UserRoleService;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

final class RoleSeeder extends Seeder
{
    private const string GUARD_NAME = 'web';

    /**
     * @throws Throwable
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (UserRole::cases() as $roleName) {
            $role = Role::findOrCreate($roleName->value, self::GUARD_NAME);
            $role->syncPermissions(
                $roleName === UserRole::ADMIN
                    ? array_map(fn (UserPermission $permission): string => $permission->value, UserPermission::cases())
                    : [],
            );
        }

        $userRoles = app(UserRoleService::class);

        User::query()
            ->doesntHave('roles')
            ->lazyById()
            ->each(fn (User $user) => $userRoles->assign($user, UserRole::USER));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
