<?php

namespace App\DataTransferObjects;

final readonly class StripeCheckoutData
{
    public function __construct(public string $id, public string $url, public int $expiresAt) {}
}
