<?php

namespace App\Services\Payments;

use App\DataTransferObjects\StripeCheckoutData;
use App\DataTransferObjects\StripeRefundData;
use App\Models\Booking;
use Stripe\StripeClient;
use Stripe\Webhook;

final readonly class StripePaymentGateway implements StripeGateway
{
    private StripeClient $client;

    public function __construct()
    {
        $this->client = new StripeClient((string) config('services.stripe.secret'));
    }

    public function createCheckout(Booking $booking): StripeCheckoutData
    {
        $session = $this->client->checkout->sessions->create([
            'mode' => 'payment', 'payment_method_types' => ['card'], 'customer_email' => $booking->purchaser_email,
            'client_reference_id' => $booking->id, 'expires_at' => $booking->hold_expires_at->getTimestamp(),
            'success_url' => rtrim((string) config('services.stripe.frontend_url'), '/').'/bookings/checkout/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => rtrim((string) config('services.stripe.frontend_url'), '/').'/bookings/checkout/cancelled',
            'metadata' => ['booking_id' => $booking->id, 'booking_reference' => $booking->reference],
            'payment_intent_data' => ['metadata' => ['booking_id' => $booking->id, 'booking_reference' => $booking->reference]],
            'line_items' => [[
                'quantity' => $booking->ticket_quantity,
                'price_data' => [
                    'currency' => $booking->currency, 'unit_amount' => $booking->unit_amount,
                    'product_data' => ['name' => $booking->tour_name],
                ],
            ]],
        ], ['idempotency_key' => 'booking-checkout-'.$booking->id]);

        return new StripeCheckoutData($session->id, (string) $session->url, (int) $session->expires_at);
    }

    public function expireCheckout(string $sessionId): void
    {
        $this->client->checkout->sessions->expire($sessionId, [], ['idempotency_key' => 'expire-'.$sessionId]);
    }

    public function retrieveCheckout(string $sessionId): array
    {
        return $this->client->checkout->sessions->retrieve($sessionId)->toArray();
    }

    public function createRefund(Booking $booking): StripeRefundData
    {
        $refund = $this->client->refunds->create([
            'payment_intent' => $booking->stripe_payment_intent_id, 'reason' => 'requested_by_customer',
            'metadata' => ['booking_id' => $booking->id, 'booking_reference' => $booking->reference],
        ], ['idempotency_key' => 'booking-refund-'.$booking->id.'-'.$booking->refund_attempt]);

        return new StripeRefundData($refund->id, (string) $refund->status, $refund->failure_reason);
    }

    public function retrieveRefund(string $refundId): StripeRefundData
    {
        $refund = $this->client->refunds->retrieve($refundId);

        return new StripeRefundData($refund->id, (string) $refund->status, $refund->failure_reason);
    }

    public function constructEvent(string $payload, string $signature): array
    {
        return Webhook::constructEvent($payload, $signature, (string) config('services.stripe.webhook_secret'))->toArray();
    }
}
