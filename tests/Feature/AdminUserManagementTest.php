<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Services\Users\UserRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function userWithRole(UserRole $role): User
{
    $user = User::factory()->create();
    app(UserRoleService::class)->assign($user, $role);

    return $user;
}

it('allows an admin to create an unverified user with a specific role', function (UserRole $role) {
    Notification::fake();
    $admin = userWithRole(UserRole::ADMIN);

    $response = $this->actingAs($admin)->postJson('/api/v1/users', [
        'name' => 'New Guide',
        'email' => ' GUIDE@EXAMPLE.COM ',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
        'role' => $role->value,
    ])->assertCreated()
        ->assertJsonPath('data.attributes.email', 'guide@example.com')
        ->assertJsonPath('data.attributes.role', $role->value)
        ->assertJsonPath('data.attributes.email_verified_at', null);

    $user = User::query()->findOrFail($response->json('data.id'));
    expect($user->hasExactRoles($role->value))->toBeTrue();
    Notification::assertSentTo($user, VerifyEmailNotification::class);
})->with(UserRole::cases());

it('allows admins to list and view users with roles', function () {
    $admin = userWithRole(UserRole::ADMIN);
    $guide = userWithRole(UserRole::GUIDE);

    $this->actingAs($admin)->getJson('/api/v1/users')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonFragment(['email' => $guide->email, 'role' => UserRole::GUIDE->value]);

    $this->actingAs($admin)->getJson('/api/v1/users/'.$guide->id)
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.attributes.role', UserRole::GUIDE->value);
});

it('returns the authenticated users account profile for every role', function (UserRole $role) {
    $user = userWithRole($role);

    $this->actingAs($user)->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.attributes.email', $user->email)
        ->assertJsonPath('data.attributes.role', $role->value)
        ->assertJsonPath('data.attributes.email_verified_at', $user->email_verified_at->toIso8601String());
})->with(UserRole::cases());

it('requires authentication for the current user profile', function () {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('allows an admin to replace another users role', function () {
    $admin = userWithRole(UserRole::ADMIN);
    $user = userWithRole(UserRole::USER);

    $this->actingAs($admin)->patchJson('/api/v1/users/'.$user->id.'/role', [
        'role' => UserRole::LEAD_GUIDE->value,
    ])->assertOk()->assertJsonPath('data.attributes.role', UserRole::LEAD_GUIDE->value);

    expect($user->fresh()->hasExactRoles(UserRole::LEAD_GUIDE->value))->toBeTrue();
});

it('allows an admin to delete another user and their authentication state', function () {
    $admin = userWithRole(UserRole::ADMIN);
    $user = userWithRole(UserRole::GUIDE);
    $user->createToken('auth-token');
    DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'token', 'created_at' => now()]);
    DB::table('sessions')->insert(['id' => 'session', 'user_id' => $user->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => now()->timestamp]);

    $this->actingAs($admin)->deleteJson('/api/v1/users/'.$user->id)->assertNoContent();

    expect(User::find($user->id))->toBeNull()
        ->and(DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->exists())->toBeFalse()
        ->and(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(DB::table('model_has_roles')->where('model_id', $user->id)->exists())->toBeFalse();
});

it('denies user management to non-admin roles', function (UserRole $role) {
    $actor = userWithRole($role);
    $target = userWithRole(UserRole::USER);

    $this->actingAs($actor)->getJson('/api/v1/users')->assertForbidden();
    $this->actingAs($actor)->getJson('/api/v1/users/'.$target->id)->assertForbidden();
    $this->actingAs($actor)->deleteJson('/api/v1/users/'.$target->id)->assertForbidden();
})->with([UserRole::USER, UserRole::GUIDE, UserRole::LEAD_GUIDE]);

it('prevents an admin from deleting or demoting their own account', function () {
    $admin = userWithRole(UserRole::ADMIN);

    $this->actingAs($admin)->patchJson('/api/v1/users/'.$admin->id.'/role', ['role' => UserRole::USER->value])->assertForbidden();
    $this->actingAs($admin)->deleteJson('/api/v1/users/'.$admin->id)->assertForbidden();

    expect($admin->fresh()->hasExactRoles(UserRole::ADMIN->value))->toBeTrue();
});

it('rejects invalid and unsupported managed user input', function () {
    $admin = userWithRole(UserRole::ADMIN);

    $this->actingAs($admin)->postJson('/api/v1/users', [
        'name' => 'User',
        'email' => 'user@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
        'role' => 'owner',
        'unexpected' => true,
    ])->assertUnprocessable()->assertJsonValidationErrors(['role', 'unexpected']);
});
