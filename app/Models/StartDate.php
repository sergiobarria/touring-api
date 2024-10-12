<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class StartDate extends Model implements Auditable
{
    use HasFactory, HasUuids, \OwenIt\Auditing\Auditable;

    protected $fillable = ['start_date', 'tour_id'];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}
