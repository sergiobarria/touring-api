<?php

namespace App\Http\Resources;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/** @mixin Booking */
final class BookingResource extends JsonApiResource
{
    public function toAttributes(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'status' => $this->status->value,
            'ticket_quantity' => $this->ticket_quantity,
            'unit_amount' => $this->unit_amount,
            'total_amount' => $this->total_amount,
            'currency' => $this->currency,
            'tour_name' => $this->tour_name,
            'departure_datetime_utc' => $this->departure_datetime_utc,
            'purchaser_name' => $this->purchaser_name,
            'purchaser_email' => $this->purchaser_email,
            'discount_percent' => $this->discount_percent,
            'travelers' => $this->travelers->map->only(['id', 'full_name', 'email', 'phone'])->values(),
            'checkout_url' => $this->status === BookingStatus::PENDING_PAYMENT ? $this->stripe_checkout_url : null,
            'hold_expires_at' => $this->hold_expires_at,
            'paid_at' => $this->paid_at,
            'cancellation_requested_at' => $this->cancellation_requested_at,
            'cancelled_at' => $this->cancelled_at,
            'cancellation_eligible' => in_array($this->status, [BookingStatus::CONFIRMED, BookingStatus::CANCELLATION_FAILED], true)
                && now('UTC')->addHours((int) config('services.stripe.cancellation_cutoff_hours'))->lte($this->departure_datetime_utc),
            'failure_code' => $this->failure_code,
        ];
    }

    public function toType(Request $request): string
    {
        return 'bookings';
    }
}
