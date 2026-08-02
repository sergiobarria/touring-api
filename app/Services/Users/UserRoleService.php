<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Auditing\AuditService;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class UserRoleService
{
    public function __construct(private AuditService $audits) {}

    /**
     * @throws Throwable
     */
    public function assign(User $user, UserRole $role): void
    {
        DB::transaction(function () use ($user, $role): void {
            $oldRoles = $user->roles()->pluck('name')->sort()->values()->all();
            $user->syncRoles($role);
            $newRoles = [$role->value];

            if ($oldRoles !== $newRoles) {
                $this->audits->record(
                    $user,
                    ['roles' => $oldRoles],
                    ['roles' => $newRoles],
                    'role-assignment',
                );
            }
        });
    }
}
