<?php

namespace App\Models;

use App\Enums\TourDifficulty;
use Database\Factories\TourFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\Attributes\Sluggable;

/**
 * @property-read float $duration_weeks
 * @property-read list<string> $upcoming_dates
 */
#[Sluggable(from: 'name', to: 'slug')]
#[Fillable([
    'name',
    'duration_days',
    'max_group_size',
    'difficulty',
    'price',
    'price_discount_percent',
    'summary',
    'description',
    'is_active',
])]
class Tour extends Model implements Auditable
{
    /** @use HasFactory<TourFactory> */
    use HasFactory, HasUlids, \OwenIt\Auditing\Auditable, SoftDeletes;

    public const array ALLOWED_SORTS = [
        'name',
        'price',
        'max_group_size',
        'duration_days',
        'created_at',
    ];

    public function startDates(): HasMany
    {
        return $this->hasMany(TourStartDate::class);
    }

    public function upcomingStartDates(): HasMany
    {
        return $this->hasMany(TourStartDate::class)
            ->where('start_datetime_utc', '>', now('UTC'))
            ->where('is_active', true)
            ->orderBy('start_datetime_utc');
    }

    #[Scope]
    protected function minPrice(Builder $query, float $price): void
    {
        $query->where('price', '>=', $price);
    }

    #[Scope]
    protected function maxPrice(Builder $query, float $price): void
    {
        $query->where('price', '<=', $price);
    }

    /**
     * Get the tour duration expressed in weeks.
     *
     * @return Attribute<float, never>
     */
    protected function durationWeeks(): Attribute
    {
        return Attribute::make(
            get: fn (): float => round($this->duration_days / 7, 1),
        );
    }

    /**
     * Get active future start dates as ISO 8601 UTC strings.
     *
     * @return Attribute<list<string>, never>
     */
    protected function upcomingDates(): Attribute
    {
        return Attribute::make(
            get: fn (): array => $this->upcomingStartDates
                ->map(
                    fn (TourStartDate $startDate): string => $startDate
                        ->start_datetime_utc
                        ->utc()
                        ->toIso8601String(),
                )
                ->values()
                ->all(),
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'difficulty' => TourDifficulty::class,
            'duration_days' => 'integer',
            'max_group_size' => 'integer',
            'price' => 'decimal:2',
            'price_discount_percent' => 'decimal:2',
            'rating_avg' => 'decimal:2',
            'rating_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
