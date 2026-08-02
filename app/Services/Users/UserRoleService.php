<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class UserRoleService
{
    public function assign(User $user, UserRole $role): void
    {
        DB::transaction(function () use ($user, $role): void {
            $user->syncRoles($role);
        });
    }
}
