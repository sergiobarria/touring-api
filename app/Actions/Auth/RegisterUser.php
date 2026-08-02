<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DataTransferObjects\AuthenticationResult;
use App\Enums\UserRole;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Models\User;
use App\Services\Auth\AccessTokenService;
use App\Services\Users\UserRoleService;
use Illuminate\Support\Facades\DB;
use Throwable;

readonly class RegisterUser
{
    public function __construct(
        private AccessTokenService $accessTokens,
        private UserRoleService $userRoles,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(RegisterRequest $request): AuthenticationResult
    {
        return DB::transaction(function () use ($request): AuthenticationResult {
            $user = User::create($request->safe()->only(['name', 'email', 'password']));
            $this->userRoles->assign($user, UserRole::USER);

            return new AuthenticationResult(
                user: $user,
                token: $this->accessTokens->issue($user),
            );
        });
    }
}
