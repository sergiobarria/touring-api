<?php

use App\Models\User;
use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

function validRegistrationPayload(array $overrides = []): array
{
    return array_replace([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ], $overrides);
}

it('registers versioned authentication routes with only logout protected', function () {
    $register = route('v1.auth.register');
    $login = route('v1.auth.login');
    $logout = route('v1.auth.logout');

    expect($register)->toEndWith('/api/v1/auth/register')
        ->and($login)->toEndWith('/api/v1/auth/login')
        ->and($logout)->toEndWith('/api/v1/auth/logout');

    $routes = collect(app('router')->getRoutes()->getRoutesByName());

    expect($routes['v1.auth.register']->methods())->toBe(['POST'])
        ->and($routes['v1.auth.login']->methods())->toBe(['POST'])
        ->and($routes['v1.auth.logout']->methods())->toBe(['POST'])
        ->and($routes['v1.auth.register']->gatherMiddleware())->toContain('throttle:5,1')
        ->and($routes['v1.auth.login']->gatherMiddleware())->not->toContain('auth:sanctum')
        ->and($routes['v1.auth.logout']->gatherMiddleware())->toContain('auth:sanctum')
        ->and($routes['v1.tours.store']->gatherMiddleware())->not->toContain('auth:sanctum');
});

it('registers a ulid user and returns an api token', function () {
    $this->freezeTime();

    $response = $this->postJson('/api/v1/auth/register', validRegistrationPayload([
        'email' => '  JANE@EXAMPLE.COM  ',
    ]));

    $response
        ->assertCreated()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.type', 'users')
        ->assertJsonPath('data.attributes.name', 'Jane Doe')
        ->assertJsonPath('data.attributes.email', 'jane@example.com')
        ->assertJsonPath('meta.token_type', 'Bearer')
        ->assertJsonPath('meta.expires_at', now()->addDays(30)->toIso8601String())
        ->assertJsonMissingPath('data.attributes.password')
        ->assertJsonMissingPath('data.attributes.remember_token')
        ->assertJsonMissingPath('data.attributes.email_verified_at')
        ->assertJsonMissingPath('data.attributes.created_at');

    $user = User::query()->sole();
    $plainTextToken = $response->json('meta.access_token');
    $token = PersonalAccessToken::findToken($plainTextToken);

    expect(Str::isUlid($response->json('data.id')))->toBeTrue()
        ->and($response->json('data.id'))->toBe($user->id)
        ->and(Hash::check('correct-horse-battery-staple', $user->password))->toBeTrue()
        ->and($token)->not->toBeNull()
        ->and($token->tokenable_id)->toBe($user->id)
        ->and($token->name)->toBe('auth-token')
        ->and($token->abilities)->toBe(['*'])
        ->and($token->expires_at->isSameSecond(now()->addDays(30)))->toBeTrue()
        ->and($token->token)->not->toBe($plainTextToken);
});

it('rejects invalid and unsupported registration input', function (array $payload, string|array $errors) {
    $this->postJson('/api/v1/auth/register', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errors);
})->with([
    'missing fields' => [[], ['name', 'email', 'password']],
    'invalid email' => [fn () => validRegistrationPayload(['email' => 'invalid']), 'email'],
    'unconfirmed password' => [fn () => validRegistrationPayload(['password_confirmation' => 'different']), 'password'],
    'short password' => [fn () => validRegistrationPayload([
        'password' => 'short',
        'password_confirmation' => 'short',
    ]), 'password'],
    'device name' => [fn () => validRegistrationPayload(['device_name' => 'Jane iPhone']), 'device_name'],
    'unsupported role' => [fn () => validRegistrationPayload(['role' => 'admin']), 'role'],
    'client user id' => [fn () => validRegistrationPayload(['id' => (string) Str::ulid()]), 'id'],
    'client abilities' => [fn () => validRegistrationPayload(['abilities' => ['admin']]), 'abilities'],
]);

it('treats email uniqueness as case insensitive', function () {
    User::factory()->create(['email' => 'jane@example.com']);

    $this->postJson('/api/v1/auth/register', validRegistrationPayload([
        'email' => 'JANE@EXAMPLE.COM',
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    expect(User::query()->count())->toBe(1);
});

it('limits registration attempts by ip', function () {
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->postJson('/api/v1/auth/register', [])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/register', [])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');
});

it('logs in with normalized credentials and preserves other tokens', function () {
    $this->freezeTime();

    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'correct-horse-battery-staple',
    ]);
    $existingToken = $user->createToken('auth-token', ['*'], now()->addDays(30));

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => ' JANE@EXAMPLE.COM ',
        'password' => 'correct-horse-battery-staple',
    ]);

    $response
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.attributes.email', 'jane@example.com')
        ->assertJsonPath('meta.token_type', 'Bearer')
        ->assertJsonPath('meta.expires_at', now()->addDays(30)->toIso8601String());

    $issuedToken = PersonalAccessToken::findToken($response->json('meta.access_token'));

    expect($issuedToken)->not->toBeNull()
        ->and($issuedToken->name)->toBe('auth-token')
        ->and($user->tokens()->count())->toBe(2)
        ->and(PersonalAccessToken::findToken($existingToken->plainTextToken))->not->toBeNull();
});

it('returns the same error for unknown users and incorrect passwords', function () {
    Sleep::fake();

    User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'correct-password',
    ]);

    $wrongPassword = $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'incorrect-password',
    ])->assertUnprocessable();

    $unknownUser = $this->postJson('/api/v1/auth/login', [
        'email' => 'unknown@example.com',
        'password' => 'incorrect-password',
    ])->assertUnprocessable();

    expect($wrongPassword->json('errors.email'))->toBe($unknownUser->json('errors.email'))
        ->and(PersonalAccessToken::query()->count())->toBe(0);

    Sleep::assertSleptTimes(2);
});

it('documents authentication requests as closed objects', function () {
    $this->withoutMiddleware(RestrictedDocsAccess::class);

    $this->getJson('/docs/v1.json')
        ->assertOk()
        ->assertJsonPath('components.schemas.RegisterRequest.additionalProperties', false)
        ->assertJsonPath('components.schemas.LoginRequest.additionalProperties', false)
        ->assertJsonMissingPath('components.schemas.RegisterRequest.properties.device_name')
        ->assertJsonMissingPath('components.schemas.LoginRequest.properties.device_name');
});

it('rejects invalid and unsupported login input', function (array $payload, string|array $errors) {
    $this->postJson('/api/v1/auth/login', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errors);
})->with([
    'missing fields' => [[], ['email', 'password']],
    'invalid email' => [['email' => 'invalid', 'password' => 'password'], 'email'],
    'device name' => [[
        'email' => 'jane@example.com',
        'password' => 'password',
        'device_name' => 'Browser',
    ], 'device_name'],
    'unsupported field' => [[
        'email' => 'jane@example.com',
        'password' => 'password',
        'remember' => true,
    ], 'remember'],
]);

it('rate limits repeated failed logins and clears attempts after success', function () {
    User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'correct-password',
    ]);

    $payload = [
        'email' => 'jane@example.com',
        'password' => 'incorrect-password',
    ];

    $this->postJson('/api/v1/auth/login', $payload)->assertUnprocessable();

    $this->postJson('/api/v1/auth/login', [
        ...$payload,
        'password' => 'correct-password',
    ])->assertOk();

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->postJson('/api/v1/auth/login', $payload)->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/login', $payload)
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');
});

it('logs out only the current token', function () {
    $user = User::factory()->create();
    $currentToken = $user->createToken('auth-token', ['*'], now()->addDays(30));
    $otherToken = $user->createToken('auth-token', ['*'], now()->addDays(30));

    $this->withToken($currentToken->plainTextToken)
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    expect(PersonalAccessToken::findToken($currentToken->plainTextToken))->toBeNull()
        ->and(PersonalAccessToken::findToken($otherToken->plainTextToken))->not->toBeNull();

    app('auth')->forgetGuards();

    $this->withToken($currentToken->plainTextToken)
        ->postJson('/api/v1/auth/logout')
        ->assertUnauthorized();

    app('auth')->forgetGuards();

    $this->withToken($otherToken->plainTextToken)
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();
});

it('rejects missing malformed and expired logout tokens', function (?string $token) {
    if ($token === 'expired') {
        $token = User::factory()->create()
            ->createToken('auth-token', ['*'], now()->subMinute())
            ->plainTextToken;
    }

    $request = $this;

    if ($token !== null) {
        $request = $this->withToken($token);
    }

    $request->postJson('/api/v1/auth/logout')->assertUnauthorized();
})->with([
    'missing token' => null,
    'malformed token' => 'not-a-sanctum-token',
    'expired token' => 'expired',
]);

it('schedules daily pruning of expired sanctum tokens', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'sanctum:prune-expired --hours=24'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 0 * * *');
});
