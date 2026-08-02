<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class RateLimitServiceProvider extends ServiceProvider
{
    private const int AUTHENTICATED_REQUESTS_PER_MINUTE = 120;

    private const int GUEST_REQUESTS_PER_MINUTE = 60;

    private const int LOGIN_REQUESTS_PER_MINUTE = 30;

    private const int PASSWORD_EMAIL_REQUESTS_PER_MINUTE = 5;

    private const int PASSWORD_RESET_REQUESTS_PER_MINUTE = 5;

    private const int REGISTRATION_REQUESTS_PER_MINUTE = 5;

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            $user = $request->user();

            if ($user !== null) {
                return Limit::perMinute(self::AUTHENTICATED_REQUESTS_PER_MINUTE)
                    ->by('user:'.$user->getAuthIdentifier());
            }

            return Limit::perMinute(self::GUEST_REQUESTS_PER_MINUTE)
                ->by('guest:'.$request->ip());
        });

        RateLimiter::for(
            'registration',
            fn (Request $request): Limit => Limit::perMinute(self::REGISTRATION_REQUESTS_PER_MINUTE)
                ->by($request->ip()),
        );

        RateLimiter::for(
            'login',
            fn (Request $request): Limit => Limit::perMinute(self::LOGIN_REQUESTS_PER_MINUTE)
                ->by($request->ip()),
        );

        RateLimiter::for(
            'password-email',
            fn (Request $request): Limit => Limit::perMinute(self::PASSWORD_EMAIL_REQUESTS_PER_MINUTE)
                ->by($request->ip()),
        );

        RateLimiter::for(
            'password-reset',
            fn (Request $request): Limit => Limit::perMinute(self::PASSWORD_RESET_REQUESTS_PER_MINUTE)
                ->by($request->ip()),
        );
    }
}
