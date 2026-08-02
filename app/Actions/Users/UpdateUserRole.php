<?php

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Users\UserRoleService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
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

        if (($user->leadTours()->exists() && $role !== UserRole::LEAD_GUIDE)
            || ($user->supportingTours()->exists() && $role !== UserRole::GUIDE)) {
            throw ValidationException::withMessages([
                'role' => 'Assigned tour guides must be replaced or removed before changing their role.',
            ]);
        }
        $this->userRoles->assign($user, $role);

        return $user->load('roles');
    }
}
