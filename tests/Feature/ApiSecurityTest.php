<?php

use App\Models\User;
use App\Providers\TelescopeServiceProvider;
use Illuminate\Cache\RateLimiter as CacheRateLimiter;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Request;
use Laravel\Telescope\Telescope;

uses(RefreshDatabase::class);

it('configures global and authentication rate limiters', function () {
    $rateLimiter = app(CacheRateLimiter::class);

    $guestRequest = Request::create('/api/v1/tours', server: ['REMOTE_ADDR' => '192.0.2.10']);
    $guestLimit = $rateLimiter->limiter('api')($guestRequest);

    $user = User::factory()->create();
    $authenticatedRequest = Request::create('/api/v1/auth/logout');
    $authenticatedRequest->setUserResolver(fn (): User => $user);
    $authenticatedLimit = $rateLimiter->limiter('api')($authenticatedRequest);

    $registrationLimit = $rateLimiter->limiter('registration')($guestRequest);
    $loginLimit = $rateLimiter->limiter('login')($guestRequest);

    expect($guestLimit->maxAttempts)->toBe(60)
        ->and($guestLimit->decaySeconds)->toBe(60)
        ->and($guestLimit->key)->toBe('guest:192.0.2.10')
        ->and($authenticatedLimit->maxAttempts)->toBe(120)
        ->and($authenticatedLimit->decaySeconds)->toBe(60)
        ->and($authenticatedLimit->key)->toBe('user:'.$user->id)
        ->and($registrationLimit->maxAttempts)->toBe(5)
        ->and($registrationLimit->key)->toBe('192.0.2.10')
        ->and($loginLimit->maxAttempts)->toBe(30)
        ->and($loginLimit->key)->toBe('192.0.2.10')
        ->and(config('cache.limiter'))->toBeNull();
});

it('limits guest api requests independently by ip and resets after decay', function () {
    $firstIp = ['REMOTE_ADDR' => '192.0.2.10'];

    for ($attempt = 1; $attempt <= 60; $attempt++) {
        $this->withServerVariables($firstIp)->getJson('/api/v1/tours')->assertOk();
    }

    $this->withServerVariables($firstIp)
        ->getJson('/api/v1/tours')
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11'])
        ->getJson('/api/v1/tours')
        ->assertOk();

    $this->travel(61)->seconds();

    $this->withServerVariables($firstIp)->getJson('/api/v1/tours')->assertOk();
});

it('limits login request volume independently from credential lockouts', function () {
    for ($attempt = 1; $attempt <= 30; $attempt++) {
        $this->postJson('/api/v1/auth/login', [])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/login', [])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');

    $this->travel(61)->seconds();

    $this->postJson('/api/v1/auth/login', [])->assertUnprocessable();
});

it('prevents caching authentication validation responses', function (string $endpoint) {
    $this->postJson($endpoint, [])
        ->assertUnprocessable()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Pragma', 'no-cache');
})->with([
    'registration' => '/api/v1/auth/register',
    'login' => '/api/v1/auth/login',
]);

it('enables the named api limiter and exact app host validation', function () {
    config()->set('app.url', 'https://api.example.com/base-path');

    $kernel = app(Kernel::class);
    $router = app('router');
    $trustedHosts = new TrustHosts(app());

    expect($router->getMiddlewareGroups()['api'])->toContain('throttle:api')
        ->and($kernel->getGlobalMiddleware())->toContain(TrustHosts::class)
        ->and($trustedHosts->hosts())->toBe(['^api\\.example\\.com$']);
});

it('redacts authentication secrets from telescope entries', function () {
    (new TelescopeServiceProvider(app()))->register();

    expect(Telescope::$hiddenRequestParameters)->toContain(
        'password',
        'password_confirmation',
        'current_password',
        'token',
    )
        ->and(Telescope::$hiddenRequestHeaders)->toContain('authorization')
        ->and(Telescope::$hiddenResponseParameters)->toContain('meta.access_token');
});
