<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class TourSchedule extends Model implements Auditable
{
    use HasUlids, HasFactory, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'tour_id',
        'start_datetime_utc',
        'is_active',
        'available_spots'
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}
