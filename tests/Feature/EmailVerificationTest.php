<?php

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('queues an encrypted frontend verification link after registration', function () {
    $this->freezeTime();
    config()->set('app.frontend_url', 'https://frontend.example.com');
    Notification::fake();

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ])->assertCreated();

    $user = User::query()->sole();

    expect($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use ($user): bool {
        $url = $notification->toMail($user)->actionUrl;
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        parse_str((string) parse_url($query['verification_url'], PHP_URL_QUERY), $verificationQuery);

        expect($notification)->toBeInstanceOf(ShouldQueue::class)
            ->toBeInstanceOf(ShouldBeEncrypted::class)
            ->and($url)->toStartWith('https://frontend.example.com/verify-email?')
            ->and($query)->toHaveKey('verification_url')
            ->and($verificationQuery['expires'])->toBe((string) now()->addMinutes(60)->timestamp)
            ->and(URL::hasValidSignature(request()->create($query['verification_url'])))->toBeTrue();

        return true;
    });
});

it('keeps registration successful when verification delivery cannot be queued', function () {
    Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('queue unavailable'));

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ])->assertCreated();

    expect(User::query()->sole()->hasVerifiedEmail())->toBeFalse();
});

it('verifies an email through a signed url without application events', function () {
    Event::fake([Verified::class]);
    $user = User::factory()->unverified()->create();
    $url = URL::temporarySignedRoute('v1.auth.verification.verify', now()->addHour(), [
        'user' => $user,
        'hash' => sha1($user->getEmailForVerification()),
    ]);

    $this->getJson($url)
        ->assertNoContent()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Pragma', 'no-cache');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertNotDispatched(Verified::class);
});

it('keeps signed email verification idempotent', function () {
    $user = User::factory()->create();
    $verifiedAt = $user->email_verified_at;
    $url = URL::temporarySignedRoute('v1.auth.verification.verify', now()->addHour(), [
        'user' => $user,
        'hash' => sha1($user->getEmailForVerification()),
    ]);

    $this->getJson($url)->assertNoContent();

    expect($user->fresh()->email_verified_at->equalTo($verifiedAt))->toBeTrue();
});

it('rejects invalid signatures and signed links for a different email hash', function () {
    $user = User::factory()->unverified()->create();
    $validUrl = URL::temporarySignedRoute('v1.auth.verification.verify', now()->addHour(), [
        'user' => $user,
        'hash' => sha1($user->getEmailForVerification()),
    ]);
    $wrongHashUrl = URL::temporarySignedRoute('v1.auth.verification.verify', now()->addHour(), [
        'user' => $user,
        'hash' => sha1('other@example.com'),
    ]);

    $this->getJson($validUrl.'&tampered=1')->assertForbidden();
    $this->getJson('/api/v1/auth/email/verify/'.Str::ulid().'/'.sha1('missing@example.com'))->assertForbidden();
    $this->getJson($wrongHashUrl)->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('returns not found for a missing user only after validating the signature', function () {
    $userId = (string) Str::ulid();
    $url = URL::temporarySignedRoute('v1.auth.verification.verify', now()->addHour(), [
        'user' => $userId,
        'hash' => sha1('missing@example.com'),
    ]);

    $this->getJson($url)->assertNotFound();
});

it('rejects an expired signed verification link', function () {
    $user = User::factory()->unverified()->create();
    $url = URL::temporarySignedRoute('v1.auth.verification.verify', now()->addMinute(), [
        'user' => $user,
        'hash' => sha1($user->getEmailForVerification()),
    ]);

    $this->travel(61)->seconds();

    $this->getJson($url)->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('resends verification only for an authenticated unverified user', function () {
    Notification::fake();
    $unverified = User::factory()->unverified()->create();
    $verified = User::factory()->create();

    $this->postJson('/api/v1/auth/email/verification-notification')->assertUnauthorized();

    $this->actingAs($unverified)
        ->postJson('/api/v1/auth/email/verification-notification')
        ->assertNoContent()
        ->assertHeader('Cache-Control', 'no-store, private');

    $this->actingAs($verified)
        ->postJson('/api/v1/auth/email/verification-notification')
        ->assertNoContent();

    Notification::assertSentTo($unverified, VerifyEmailNotification::class);
    Notification::assertNotSentTo($verified, VerifyEmailNotification::class);
});

it('rejects fields on verification resend requests', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/auth/email/verification-notification', ['email' => $user->email])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('limits verification resends per authenticated user and resets after decay', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    for ($attempt = 1; $attempt <= 6; $attempt++) {
        $this->actingAs($user)
            ->postJson('/api/v1/auth/email/verification-notification')
            ->assertNoContent();
    }

    $this->actingAs($user)
        ->postJson('/api/v1/auth/email/verification-notification')
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');

    $this->travel(61)->seconds();

    $this->actingAs($user)
        ->postJson('/api/v1/auth/email/verification-notification')
        ->assertNoContent();
});
