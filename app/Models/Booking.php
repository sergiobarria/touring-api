<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

#[Fillable([
    'user_id', 'tour_id', 'tour_start_date_id', 'reference', 'status', 'ticket_quantity',
    'unit_amount', 'total_amount', 'currency', 'tour_name', 'departure_datetime_utc',
    'purchaser_name', 'purchaser_email', 'discount_percent', 'idempotency_key_hash',
    'request_hash', 'stripe_checkout_session_id', 'stripe_checkout_url',
    'stripe_payment_intent_id', 'stripe_refund_id', 'stripe_refund_status',
    'refund_attempt', 'hold_expires_at', 'paid_at', 'cancellation_requested_at', 'cancelled_at', 'failure_code',
])]
class Booking extends Model implements Auditable
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory, HasUlids, \OwenIt\Auditing\Auditable;

    protected array $auditExclude = ['idempotency_key_hash', 'request_hash', 'stripe_checkout_url'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function tourStartDate(): BelongsTo
    {
        return $this->belongsTo(TourStartDate::class);
    }

    public function travelers(): HasMany
    {
        return $this->hasMany(BookingTraveler::class);
    }

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'ticket_quantity' => 'integer',
            'unit_amount' => 'integer',
            'total_amount' => 'integer',
            'refund_attempt' => 'integer',
            'discount_percent' => 'decimal:2',
            'departure_datetime_utc' => 'immutable_datetime',
            'hold_expires_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'cancellation_requested_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}
