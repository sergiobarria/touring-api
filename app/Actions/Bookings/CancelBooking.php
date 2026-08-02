<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingCancellationFailedNotification;
use App\Notifications\BookingCancelledNotification;
use App\Services\Bookings\BookingService;
use App\Services\Payments\StripeGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class CancelBooking
{
    public function __construct(private BookingService $bookings, private StripeGateway $stripe) {}

    /** @throws Throwable */
    public function handle(User $user, string $bookingId): Booking
    {
        $booking = DB::transaction(function () use ($user, $bookingId): Booking {
            $booking = Booking::query()->whereBelongsTo($user)->lockForUpdate()->findOrFail($bookingId);
            if ($booking->status === BookingStatus::CANCELLED || $booking->status === BookingStatus::CANCELLATION_PENDING) {
                return $booking;
            }
            if (! in_array($booking->status, [BookingStatus::CONFIRMED, BookingStatus::CANCELLATION_FAILED], strict: true)) {
                throw ValidationException::withMessages(['booking' => 'This booking cannot be cancelled.']);
            }
            $cutoff = $booking->departure_datetime_utc->subHours((int) config('services.stripe.cancellation_cutoff_hours'));
            if ($booking->cancellation_requested_at === null && now('UTC')->gt($cutoff)) {
                throw ValidationException::withMessages(['booking' => 'The cancellation cutoff has passed.']);
            }
            $booking->cancellation_requested_at ??= now('UTC');
            if ($booking->total_amount === 0) {
                $this->bookings->releaseSeats($booking);
                $booking->fill(['status' => BookingStatus::CANCELLED, 'cancelled_at' => now('UTC')])->save();
                $user->notify(new BookingCancelledNotification($booking));

                return $booking;
            }
            $booking->fill([
                'status' => BookingStatus::CANCELLATION_PENDING,
                'refund_attempt' => $booking->refund_attempt + 1,
                'failure_code' => null,
            ])->save();

            return $booking;
        });

        if ($booking->status !== BookingStatus::CANCELLATION_PENDING || $booking->stripe_refund_status === 'pending') {
            return $booking->load('travelers');
        }

        try {
            $refund = $this->stripe->createRefund($booking);
            DB::transaction(function () use ($booking, $refund): void {
                $booking = Booking::query()->with('user')->lockForUpdate()->findOrFail($booking->id);
                $booking->stripe_refund_id = $refund->id;
                $this->bookings->applyRefundStatus($booking, $refund->status, $refund->failureReason);
            });
        } catch (Throwable $exception) {
            DB::transaction(function () use ($booking): void {
                $booking = Booking::query()->with('user')->lockForUpdate()->findOrFail($booking->id);
                $booking->update([
                    'status' => BookingStatus::CANCELLATION_FAILED,
                    'failure_code' => 'refund_request_failed',
                ]);
                $booking->user->notify(new BookingCancellationFailedNotification($booking));
            });
            throw $exception;
        }

        return $booking->refresh()->load('travelers');
    }
}
