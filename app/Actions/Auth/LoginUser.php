<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DataTransferObjects\AuthenticationResult;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Models\User;
use App\Services\Auth\AccessTokenService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;

readonly class LoginUser
{
    private const int MAX_ATTEMPTS = 5;

    private const int DECAY_SECONDS = 60;

    private const int TIMEBOX_DURATION_MICROSECONDS = 200_000;

    public function __construct(
        private AccessTokenService $accessTokens,
        private Timebox $timebox,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(LoginRequest $request): AuthenticationResult
    {
        $this->ensureIsNotRateLimited($request);

        $password = (string) $request->string('password');
        $user = $this->validateCredentials(
            email: (string) $request->string('email'),
            password: $password,
        );

        if (! $user) {
            RateLimiter::hit($this->throttleKey($request), self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        if (Hash::needsRehash($user->getAuthPassword())) {
            $user->forceFill(['password' => Hash::make($password)])->save();
        }

        return new AuthenticationResult(
            user: $user,
            token: $this->accessTokens->issue($user),
        );
    }

    private function ensureIsNotRateLimited(LoginRequest $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($request));

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw new ThrottleRequestsException(
            trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
            headers: [
                'Retry-After' => $seconds,
                'X-RateLimit-Reset' => now()->addSeconds($seconds)->getTimestamp(),
            ],
        );
    }

    private function validateCredentials(string $email, #[\SensitiveParameter] string $password): ?User
    {
        return $this->timebox->dontReturnEarly()->call(
            function (Timebox $timebox) use ($email, $password): ?User {
                $user = User::query()->where('email', $email)->first();

                if (! $user || ! Hash::check($password, $user->getAuthPassword())) {
                    return null;
                }

                $timebox->returnEarly();

                return $user;
            },
            self::TIMEBOX_DURATION_MICROSECONDS,
        );
    }

    private function throttleKey(LoginRequest $request): string
    {
        return Str::transliterate(Str::lower((string) $request->string('email')).'|'.$request->ip());
    }
}
