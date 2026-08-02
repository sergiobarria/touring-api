<?php

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Users\UserRoleService;
use Illuminate\Support\Facades\Gate;
use Throwable;

final readonly class UpdateUserRole
{
    public function __construct(private UserRoleService $userRoles) {}

    /**
     * @throws Throwable
     */
    public function handle(string $userId, UserRole $role): User
    {
        $user = User::query()->findOrFail($userId);
        Gate::authorize('updateRole', $user);
        $this->userRoles->assign($user, $role);

        return $user->load('roles');
    }
}
