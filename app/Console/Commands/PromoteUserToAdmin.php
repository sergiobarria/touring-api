<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Users\UserRoleService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('users:promote-admin {user : User ULID or email address}')]
#[Description('Promote an existing user to the admin role')]
final class PromoteUserToAdmin extends Command
{
    public function handle(UserRoleService $userRoles): int
    {
        $identifier = trim((string) $this->argument('user'));
        $user = User::query()
            ->whereKey($identifier)
            ->orWhere('email', Str::lower($identifier))
            ->first();

        if ($user === null) {
            $this->error('No user was found for the provided ULID or email address.');

            return self::FAILURE;
        }

        $userRoles->assign($user, UserRole::ADMIN);

        $this->info("{$user->email} now has the admin role.");

        return self::SUCCESS;
    }
}
