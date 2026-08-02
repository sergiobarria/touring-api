<?php

namespace App\Actions\Bookings;

use App\Models\Booking;
use App\Services\Bookings\BookingService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final readonly class HandleStripeWebhook
{
    public function __construct(private BookingService $bookings) {}

    /** @param array<string, mixed> $event
     * @throws Throwable
     */
    public function handle(array $event): void
    {
        DB::transaction(function () use ($event): void {
            $claimed = DB::table('stripe_webhook_events')->insertOrIgnore([
                'id' => $event['id'], 'type' => $event['type'], 'processed_at' => now('UTC'),
            ]);
            if ($claimed === 0) {
                return;
            }
            $object = $event['data']['object'];
            match ($event['type']) {
                'checkout.session.completed' => $this->completeCheckout($object),
                'checkout.session.expired' => $this->expireCheckout($object),
                'refund.updated', 'refund.failed' => $this->updateRefund($object),
                default => null,
            };
        });
    }

    /** @param array<string, mixed> $session */
    private function completeCheckout(array $session): void
    {
        $booking = Booking::query()->with('user')->lockForUpdate()->findOrFail($session['metadata']['booking_id'] ?? '');
        $this->bookings->confirmCheckout($booking, $session);
    }

    /** @param array<string, mixed> $session */
    private function expireCheckout(array $session): void
    {
        $booking = Booking::query()->lockForUpdate()->where('stripe_checkout_session_id', $session['id'])->first();
        if ($booking !== null) {
            $this->bookings->expire($booking);
        }
    }

    /** @param array<string, mixed> $refund */
    private function updateRefund(array $refund): void
    {
        $booking = Booking::query()->with('user')->lockForUpdate()->where('stripe_refund_id', $refund['id'])->first();
        if ($booking === null && isset($refund['metadata']['booking_id'])) {
            $booking = Booking::query()->with('user')->whereNull('stripe_refund_id')
                ->lockForUpdate()->find($refund['metadata']['booking_id']);
            if ($booking !== null) {
                $booking->stripe_refund_id = $refund['id'];
            }
        }
        if ($booking !== null) {
            if (($refund['payment_intent'] ?? null) !== $booking->stripe_payment_intent_id
                || (int) ($refund['amount'] ?? -1) !== $booking->total_amount
                || strtolower((string) ($refund['currency'] ?? '')) !== $booking->currency) {
                throw new RuntimeException('Stripe Refund does not match the booking.');
            }
            $this->bookings->applyRefundStatus($booking, (string) $refund['status'], $refund['failure_reason'] ?? null);
        }
    }
}
