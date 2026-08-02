<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ListUsers
{
    /** @return LengthAwarePaginator<int, User> */
    public function handle(int $perPage, int $page): LengthAwarePaginator
    {
        return User::query()
            ->with('roles')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(perPage: $perPage, page: $page);
    }
}
