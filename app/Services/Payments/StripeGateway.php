<?php

namespace App\Services\Payments;

use App\DataTransferObjects\StripeCheckoutData;
use App\DataTransferObjects\StripeRefundData;
use App\Models\Booking;

interface StripeGateway
{
    public function createCheckout(Booking $booking): StripeCheckoutData;

    public function expireCheckout(string $sessionId): void;

    /** @return array<string, mixed> */
    public function retrieveCheckout(string $sessionId): array;

    public function createRefund(Booking $booking): StripeRefundData;

    public function retrieveRefund(string $refundId): StripeRefundData;

    /** @return array<string, mixed> */
    public function constructEvent(string $payload, string $signature): array;
}
