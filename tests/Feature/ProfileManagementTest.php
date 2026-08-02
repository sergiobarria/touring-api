<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Services\Users\UserRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('allows every role to update its own name', function (UserRole $role) {
    $user = User::factory()->create();
    app(UserRoleService::class)->assign($user, $role);

    $this->actingAs($user)->patchJson('/api/v1/auth/me', ['name' => 'Updated Name'])
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.attributes.name', 'Updated Name')
        ->assertJsonPath('data.attributes.role', $role->value);

    expect($user->fresh()->name)->toBe('Updated Name');
})->with(UserRole::cases());

it('requires the current password for an email change and sends fresh verification', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'old@example.com', 'password' => 'password']);
    app(UserRoleService::class)->assign($user, UserRole::GUIDE);
    $token = $user->createToken('auth-token');
    DB::table('password_reset_tokens')->insert([
        ['email' => 'old@example.com', 'token' => 'old-token', 'created_at' => now()],
        ['email' => 'new@example.com', 'token' => 'stale-token', 'created_at' => now()],
    ]);

    $this->withToken($token->plainTextToken)->patchJson('/api/v1/auth/me', ['email' => 'new@example.com'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');

    $this->withToken($token->plainTextToken)->patchJson('/api/v1/auth/me', [
        'email' => ' NEW@EXAMPLE.COM ',
        'current_password' => 'password',
    ])->assertOk()
        ->assertJsonPath('data.attributes.email', 'new@example.com')
        ->assertJsonPath('data.attributes.email_verified_at', null);

    $user->refresh();
    expect($user->hasVerifiedEmail())->toBeFalse()
        ->and(DB::table('password_reset_tokens')->whereIn('email', ['old@example.com', 'new@example.com'])->exists())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

it('rejects an incorrect password for an email change', function () {
    $user = User::factory()->create(['password' => 'password']);
    app(UserRoleService::class)->assign($user, UserRole::USER);

    $this->actingAs($user)->patchJson('/api/v1/auth/me', [
        'email' => 'new@example.com',
        'current_password' => 'incorrect',
    ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
});

it('does not require verification again when the normalized email is unchanged', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'user@example.com']);
    app(UserRoleService::class)->assign($user, UserRole::LEAD_GUIDE);

    $this->actingAs($user)->patchJson('/api/v1/auth/me', ['email' => ' USER@EXAMPLE.COM '])
        ->assertOk()
        ->assertJsonPath('data.attributes.email_verified_at', $user->email_verified_at->toIso8601String());

    Notification::assertNothingSent();
});

it('rejects duplicate emails, empty updates, and unsupported profile fields', function () {
    User::factory()->create(['email' => 'existing@example.com']);
    $user = User::factory()->create(['email' => 'user@example.com']);
    app(UserRoleService::class)->assign($user, UserRole::USER);

    $this->actingAs($user)->patchJson('/api/v1/auth/me', [
        'email' => 'existing@example.com',
        'current_password' => 'password',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->actingAs($user)->patchJson('/api/v1/auth/me', [])
        ->assertUnprocessable()->assertJsonValidationErrors('profile');

    $this->actingAs($user)->patchJson('/api/v1/auth/me', ['bio' => 'Unsupported'])
        ->assertUnprocessable()->assertJsonValidationErrors(['bio', 'profile']);
});

it('maps a database email uniqueness race to validation', function () {
    $user = User::factory()->create(['email' => 'user@example.com']);
    app(UserRoleService::class)->assign($user, UserRole::USER);

    DB::statement(<<<'SQL'
        CREATE TRIGGER simulate_profile_email_race
        BEFORE UPDATE OF email ON users
        WHEN NEW.email = 'race@example.com'
        BEGIN
            SELECT RAISE(ABORT, 'UNIQUE constraint failed: users.email');
        END
        SQL);

    $this->actingAs($user)->patchJson('/api/v1/auth/me', [
        'email' => 'race@example.com',
        'current_password' => 'password',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');

    expect($user->fresh()->email)->toBe('user@example.com');
});

it('requires authentication to update a profile', function () {
    $this->patchJson('/api/v1/auth/me', ['name' => 'Updated'])->assertUnauthorized();
});

it('limits sensitive account updates per authenticated user and resets after decay', function () {
    $user = User::factory()->create();
    app(UserRoleService::class)->assign($user, UserRole::USER);

    for ($attempt = 1; $attempt <= 10; $attempt++) {
        $this->actingAs($user)->patchJson('/api/v1/auth/me', [])->assertUnprocessable();
    }

    $this->actingAs($user)->patchJson('/api/v1/auth/me', [])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');

    $this->travel(61)->seconds();
    $this->actingAs($user)->patchJson('/api/v1/auth/me', [])->assertUnprocessable();
});
