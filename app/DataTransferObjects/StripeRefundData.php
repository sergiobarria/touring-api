<?php

namespace App\DataTransferObjects;

final readonly class StripeRefundData
{
    public function __construct(public string $id, public string $status, public ?string $failureReason = null) {}
}
