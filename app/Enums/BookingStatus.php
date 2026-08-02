<?php

namespace App\Enums;

enum BookingStatus: string
{
    case PENDING_PAYMENT = 'pending_payment';
    case CONFIRMED = 'confirmed';
    case CANCELLATION_PENDING = 'cancellation_pending';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case CANCELLATION_FAILED = 'cancellation_failed';
}
