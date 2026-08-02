<?php

namespace App\Models;

use Database\Factories\TourStartDateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

#[Fillable([
    'start_datetime_utc',
    'available_spots',
    'is_active',
])]
class TourStartDate extends Model implements Auditable
{
    /** @use HasFactory<TourStartDateFactory> */
    use HasFactory, HasUlids, \OwenIt\Auditing\Auditable, SoftDeletes;

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_datetime_utc' => 'immutable_datetime',
            'available_spots' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
