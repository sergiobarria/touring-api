<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Sleep;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

it('sends a frontend password reset link without exposing account existence', function () {
    config()->set('app.frontend_url', 'https://frontend.example.com');
    Sleep::fake();
    Notification::fake();
    $user = User::factory()->create(['email' => 'jane@example.com']);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => ' JANE@EXAMPLE.COM '])->assertAccepted();
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'missing@example.com'])->assertAccepted();

    Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user): bool {
        expect($notification)->toBeInstanceOf(ShouldQueue::class)
            ->toBeInstanceOf(ShouldBeEncrypted::class)
            ->and($notification->toMail($user)->actionUrl)
            ->toStartWith('https://frontend.example.com/reset-password?')
            ->toContain('email=jane%40example.com');

        return true;
    });
    Sleep::assertSleptTimes(2);
});

it('keeps the forgot password response generic when delivery cannot be queued', function () {
    Password::shouldReceive('sendResetLink')->once()->andThrow(new RuntimeException('queue unavailable'));

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'jane@example.com'])
        ->assertAccepted()
        ->assertJsonPath('message', 'If an account exists, a password reset link has been sent.');
});

it('resets a password and revokes every access token', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);
    $user->createToken('auth-token');
    $token = Password::createToken($user);

    $this->postJson('/api/v1/auth/reset-password', [
        'email' => $user->email,
        'token' => $token,
        'password' => 'new-correct-horse-battery-staple',
        'password_confirmation' => 'new-correct-horse-battery-staple',
    ])->assertNoContent();

    expect(Hash::check('new-correct-horse-battery-staple', $user->fresh()->password))->toBeTrue()
        ->and(PersonalAccessToken::query()->count())->toBe(0);
});

it('changes the authenticated password and preserves only the current token', function () {
    $user = User::factory()->create(['password' => 'current-password']);
    $current = $user->createToken('auth-token');
    $other = $user->createToken('auth-token');

    $this->withToken($current->plainTextToken)->putJson('/api/v1/auth/password', [
        'current_password' => 'current-password',
        'password' => 'new-correct-horse-battery-staple',
        'password_confirmation' => 'new-correct-horse-battery-staple',
    ])->assertNoContent();

    expect(Hash::check('new-correct-horse-battery-staple', $user->fresh()->password))->toBeTrue()
        ->and(PersonalAccessToken::findToken($current->plainTextToken))->not->toBeNull()
        ->and(PersonalAccessToken::findToken($other->plainTextToken))->toBeNull();
});
