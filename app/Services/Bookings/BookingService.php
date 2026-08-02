<?php

namespace App\Services\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\TourStartDate;
use App\Notifications\BookingCancellationFailedNotification;
use App\Notifications\BookingCancelledNotification;
use App\Notifications\BookingConfirmedNotification;
use RuntimeException;

final readonly class BookingService
{
    public function unitAmount(string $price, ?string $discount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $price, 2), 2, '');
        $baseCents = ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
        if ($discount === null) {
            return $baseCents;
        }
        [$discountWhole, $discountFraction] = array_pad(explode('.', $discount, 2), 2, '');
        $basisPoints = ((int) $discountWhole * 100) + (int) str_pad(substr($discountFraction, 0, 2), 2, '0');

        return (int) round($baseCents * (10000 - $basisPoints) / 10000, 0, PHP_ROUND_HALF_UP);
    }

    public function releaseSeats(Booking $booking): void
    {
        $startDate = TourStartDate::query()->lockForUpdate()->findOrFail($booking->tour_start_date_id);
        if ($startDate->reserved_spots < $booking->ticket_quantity) {
            throw new RuntimeException('The departure does not contain enough reserved spots to release this booking.');
        }

        $startDate->decrement('reserved_spots', $booking->ticket_quantity);
        $startDate->increment('available_spots', $booking->ticket_quantity);
    }

    /** @param array<string, mixed> $session */
    public function confirmCheckout(Booking $booking, array $session): void
    {
        if ($booking->status === BookingStatus::CONFIRMED) {
            if ($booking->stripe_checkout_session_id !== $session['id']) {
                throw new RuntimeException('Stripe Checkout Session does not match the booking.');
            }

            return;
        }

        if ($booking->status !== BookingStatus::PENDING_PAYMENT
            || $booking->stripe_checkout_session_id !== $session['id']
            || ($session['client_reference_id'] ?? null) !== $booking->id
            || ($session['metadata']['booking_reference'] ?? null) !== $booking->reference
            || ($session['payment_status'] ?? null) !== 'paid'
            || (int) ($session['amount_total'] ?? -1) !== $booking->total_amount
            || strtolower((string) ($session['currency'] ?? '')) !== $booking->currency) {
            throw new RuntimeException('Stripe Checkout Session does not match the booking.');
        }

        $booking->update([
            'status' => BookingStatus::CONFIRMED,
            'stripe_checkout_url' => null,
            'stripe_payment_intent_id' => $session['payment_intent'],
            'paid_at' => now('UTC'),
            'failure_code' => null,
        ]);
        $booking->user->notify(new BookingConfirmedNotification($booking));
    }

    public function expire(Booking $booking): bool
    {
        if ($booking->status !== BookingStatus::PENDING_PAYMENT) {
            return false;
        }
        $this->releaseSeats($booking);
        $booking->update(['status' => BookingStatus::EXPIRED, 'stripe_checkout_url' => null, 'failure_code' => 'checkout_expired']);

        return true;
    }

    public function applyRefundStatus(Booking $booking, string $status, ?string $failureReason = null): void
    {
        if ($status === 'succeeded') {
            if ($booking->status === BookingStatus::CANCELLED) {
                return;
            }
            $this->releaseSeats($booking);
            $booking->update([
                'status' => BookingStatus::CANCELLED, 'stripe_refund_status' => $status,
                'cancelled_at' => now('UTC'), 'failure_code' => null,
            ]);
            $booking->user->notify(new BookingCancelledNotification($booking));

            return;
        }

        if (in_array($status, ['failed', 'canceled'], strict: true)) {
            if ($booking->status === BookingStatus::CANCELLATION_FAILED && $booking->stripe_refund_status === $status) {
                return;
            }
            $booking->update([
                'status' => BookingStatus::CANCELLATION_FAILED, 'stripe_refund_status' => $status,
                'failure_code' => $failureReason ?? 'refund_'.$status,
            ]);
            $booking->user->notify(new BookingCancellationFailedNotification($booking));

            return;
        }

        $booking->update(['status' => BookingStatus::CANCELLATION_PENDING, 'stripe_refund_status' => $status]);
    }
}
