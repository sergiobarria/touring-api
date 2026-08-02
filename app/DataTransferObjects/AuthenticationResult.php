<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

final readonly class AuthenticationResult
{
    public function __construct(
        public User $user,
        public NewAccessToken $token,
    ) {}
}
