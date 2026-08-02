<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['full_name', 'email', 'phone'])]
class BookingTraveler extends Model
{
    use HasUlids;

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
