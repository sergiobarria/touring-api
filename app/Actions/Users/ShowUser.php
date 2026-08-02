<?php

namespace App\Actions\Users;

use App\Enums\UserPermission;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final readonly class ShowUser
{
    public function handle(string $userId): User
    {
        Gate::authorize(UserPermission::VIEW->value);
        $user = User::query()->with('roles')->findOrFail($userId);
        Gate::authorize('view', $user);

        return $user;
    }
}
