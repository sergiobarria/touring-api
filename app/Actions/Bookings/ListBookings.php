<?php

namespace App\Actions\Bookings;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ListBookings
{
    public function handle(User $user, int $perPage, int $page): LengthAwarePaginator
    {
        return $user->bookings()->with('travelers')->latest()->paginate($perPage, ['*'], 'page', $page);
    }
}
