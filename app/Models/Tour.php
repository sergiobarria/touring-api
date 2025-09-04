<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Tour extends Model implements Auditable
{
    use HasUlids, HasFactory, HasSlug, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'name',
        'duration_days',
        'max_group_size',
        'difficulty',
        'rating_avg',
        'rating_count',
        'price',
        'price_discount_percent',
        'summary',
        'description'
    ];

    protected $appends = ['upcoming_dates', 'duration_weeks'];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function getUpcomingDatesAttribute(): Collection
    {
        return $this->schedules()
            ->where('start_datetime_utc', '>', now())
            ->where('is_active', true)
            ->orderBy('start_datetime_utc')
            ->get()
            ->pluck('start_datetime_utc');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(TourSchedule::class);
    }

    public function scopeMinPrice($query, $price)
    {
        return $query->where('price', '>=', $price);
    }

    public function scopeMaxPrice($query, $price)
    {
        return $query->where('price', '<=', $price);
    }

    public function getDurationWeeksAttribute(): int
    {
        return round($this->duration_days / 7, 1);
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
