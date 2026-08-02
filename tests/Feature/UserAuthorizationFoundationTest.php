<?php

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Users\UserRoleService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('seeds the canonical roles and user permissions idempotently', function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);

    expect(Role::query()->orderBy('name')->pluck('name')->all())
        ->toBe(collect(UserRole::cases())->pluck('value')->sort()->values()->all())
        ->and(Permission::query()->orderBy('name')->pluck('name')->all())
        ->toBe(collect(UserPermission::cases())->pluck('value')->sort()->values()->all());
});

it('grants user management permissions only to admins', function (UserRole $role) {
    $user = User::factory()->create();
    app(UserRoleService::class)->assign($user, $role);

    foreach (UserPermission::cases() as $permission) {
        expect($user->can($permission->value))->toBe($role === UserRole::ADMIN);
    }
})->with(UserRole::cases());

it('replaces a users role and enforces one role in the database', function () {
    $user = User::factory()->create();
    $userRoles = app(UserRoleService::class);

    $userRoles->assign($user, UserRole::USER);
    $userRoles->assign($user, UserRole::LEAD_GUIDE);

    expect($user->fresh()->hasExactRoles(UserRole::LEAD_GUIDE->value))->toBeTrue()
        ->and(DB::table('model_has_roles')->where('model_id', $user->id)->count())->toBe(1);

    expect(fn () => $user->fresh()->assignRole(UserRole::ADMIN))
        ->toThrow(QueryException::class);
});

it('preserves the current role when replacement fails', function () {
    $user = User::factory()->create();
    $userRoles = app(UserRoleService::class);
    $userRoles->assign($user, UserRole::GUIDE);

    $adminRoleId = (int) Role::findByName(UserRole::ADMIN->value)->getKey();

    DB::statement(<<<SQL
        CREATE TRIGGER fail_admin_role_assignment
        BEFORE INSERT ON model_has_roles
        WHEN NEW.role_id = {$adminRoleId}
        BEGIN
            SELECT RAISE(ABORT, 'simulated role assignment failure');
        END
        SQL);

    expect(fn () => $userRoles->assign($user, UserRole::ADMIN))
        ->toThrow(QueryException::class);

    expect($user->fresh()->hasExactRoles(UserRole::GUIDE->value))->toBeTrue()
        ->and(DB::table('model_has_roles')->where('model_id', $user->id)->count())->toBe(1);
});

it('backfills users without roles as users', function () {
    $user = User::factory()->create();

    expect($user->roles)->toBeEmpty();

    $this->seed(RoleSeeder::class);

    expect($user->fresh()->hasExactRoles(UserRole::USER->value))->toBeTrue();
});

it('promotes an existing user to the sole admin role', function (string $identifier) {
    $user = User::factory()->create(['email' => 'guide@example.com']);
    app(UserRoleService::class)->assign($user, UserRole::GUIDE);

    $this->artisan('users:promote-admin', [
        'user' => $identifier === 'ulid' ? $user->id : ' GUIDE@EXAMPLE.COM ',
    ])
        ->expectsOutput('guide@example.com now has the admin role.')
        ->assertSuccessful();

    expect($user->fresh()->hasExactRoles(UserRole::ADMIN->value))->toBeTrue();
})->with(['email', 'ulid']);

it('fails admin promotion when the user does not exist', function () {
    $this->artisan('users:promote-admin', ['user' => 'missing@example.com'])
        ->expectsOutput('No user was found for the provided ULID or email address.')
        ->assertFailed();
});
