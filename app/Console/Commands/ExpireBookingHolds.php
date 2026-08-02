<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\Bookings\BookingService;
use App\Services\Payments\StripeGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ExpireBookingHolds extends Command
{
    protected $signature = 'bookings:expire-holds';

    protected $description = 'Expire overdue booking holds and restore their seats';

    public function handle(BookingService $bookings, StripeGateway $stripe): int
    {
        Booking::query()->where('status', BookingStatus::PENDING_PAYMENT)->where('hold_expires_at', '<=', now('UTC'))
            ->pluck('id')->each(function (string $id) use ($bookings, $stripe): void {
                $booking = Booking::query()->find($id);
                if ($booking === null || $booking->stripe_checkout_session_id === null) {
                    return;
                }

                try {
                    $stripe->expireCheckout($booking->stripe_checkout_session_id);
                    $session = $stripe->retrieveCheckout($booking->stripe_checkout_session_id);
                } catch (Throwable $expirationException) {
                    try {
                        $session = $stripe->retrieveCheckout($booking->stripe_checkout_session_id);
                    } catch (Throwable $retrievalException) {
                        report($expirationException);
                        report($retrievalException);

                        return;
                    }
                }

                DB::transaction(function () use ($id, $bookings, $session): void {
                    $booking = Booking::query()->with('user')->lockForUpdate()->find($id);
                    if ($booking === null || $booking->status !== BookingStatus::PENDING_PAYMENT) {
                        return;
                    }

                    if (($session['payment_status'] ?? null) === 'paid') {
                        $bookings->confirmCheckout($booking, $session);

                        return;
                    }

                    if (($session['status'] ?? null) === 'expired') {
                        $bookings->expire($booking);
                    }
                });
            });

        return self::SUCCESS;
    }
}
