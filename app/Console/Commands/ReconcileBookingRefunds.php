<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\Bookings\BookingService;
use App\Services\Payments\StripeGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ReconcileBookingRefunds extends Command
{
    protected $signature = 'bookings:reconcile-refunds';

    protected $description = 'Reconcile pending booking refunds with Stripe';

    public function handle(BookingService $bookings, StripeGateway $stripe): int
    {
        Booking::query()->where('status', BookingStatus::CANCELLATION_PENDING)->whereNotNull('stripe_refund_id')
            ->pluck('id')->each(function (string $id) use ($bookings, $stripe): void {
                $booking = Booking::query()->find($id);
                if ($booking === null) {
                    return;
                }
                try {
                    $refund = $stripe->retrieveRefund($booking->stripe_refund_id);
                } catch (Throwable $exception) {
                    report($exception);

                    return;
                }
                DB::transaction(function () use ($id, $refund, $bookings): void {
                    $booking = Booking::query()->with('user')->lockForUpdate()->find($id);
                    if ($booking?->status === BookingStatus::CANCELLATION_PENDING) {
                        $bookings->applyRefundStatus($booking, $refund->status, $refund->failureReason);
                    }
                });
            });

        return self::SUCCESS;
    }
}
