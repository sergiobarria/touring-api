<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

readonly class AccessTokenService
{
    private const string TOKEN_NAME = 'auth-token';

    /** @var list<string> */
    private const array ABILITIES = ['*'];

    public function issue(User $user): NewAccessToken
    {
        return $user->createToken(
            self::TOKEN_NAME,
            self::ABILITIES,
            now()->addMinutes((int) config('sanctum.expiration')),
        );
    }

    public function revokeCurrent(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
