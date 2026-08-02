<?php

namespace App\Policies;

use App\Enums\UserPermission;
use App\Models\User;

final readonly class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(UserPermission::VIEW_ANY->value);
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->can(UserPermission::VIEW->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(UserPermission::CREATE->value);
    }

    public function updateRole(User $actor, User $user): bool
    {
        return ! $actor->is($user) && $actor->can(UserPermission::UPDATE_ROLE->value);
    }

    public function delete(User $actor, User $user): bool
    {
        return ! $actor->is($user) && $actor->can(UserPermission::DELETE->value);
    }
}
