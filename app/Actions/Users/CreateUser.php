<?php

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use App\Services\Users\UserRoleService;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class CreateUser
{
    public function __construct(
        private EmailVerificationService $emailVerification,
        private UserRoleService $userRoles,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(StoreUserRequest $request): User
    {
        $user = DB::transaction(function () use ($request): User {
            $user = User::create($request->safe()->only(['name', 'email', 'password']));
            $this->userRoles->assign($user, UserRole::from((string) $request->string('role')));

            return $user;
        });

        $this->emailVerification->send($user);

        return $user->load('roles');
    }
}
