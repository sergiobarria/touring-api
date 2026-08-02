<?php

namespace App\Actions\Bookings;

use App\Models\Booking;
use App\Models\User;

final readonly class ShowBooking
{
    public function handle(User $user, string $bookingId): Booking
    {
        return $user->bookings()->with('travelers')->findOrFail($bookingId);
    }
}
