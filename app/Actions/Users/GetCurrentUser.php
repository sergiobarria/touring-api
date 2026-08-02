<?php

namespace App\Actions\Users;

use App\Models\User;

final readonly class GetCurrentUser
{
    public function handle(User $user): User
    {
        return $user->load('roles');
    }
}
