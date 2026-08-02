<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\Auth\AccessTokenService;
use Illuminate\Http\Request;

readonly class LogoutUser
{
    public function __construct(
        private AccessTokenService $accessTokens,
    ) {}

    public function handle(Request $request): void
    {
        $user = $request->user();

        if ($user instanceof User) {
            $this->accessTokens->revokeCurrent($user);
        }
    }
}
