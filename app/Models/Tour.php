<?php

namespace App\Models;

use App\DataTransferObjects\TourImageData;
use App\Enums\TourDifficulty;
use Database\Factories\TourFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sluggable\Attributes\Sluggable;

/**
 * @property-read float $duration_weeks
 * @property-read list<array{id: int, position: int, is_cover: bool, name: string, mime_type: string|null, size_bytes: int, original_url: string, card_url: string, thumbnail_url: string}> $images
 * @property-read list<string> $upcoming_dates
 */
#[Sluggable(from: 'name', to: 'slug')]
#[Fillable([
    'name',
    'lead_guide_id',
    'duration_days',
    'max_group_size',
    'difficulty',
    'price',
    'price_discount_percent',
    'summary',
    'description',
    'is_active',
])]
class Tour extends Model implements Auditable, HasMedia
{
    /** @use HasFactory<TourFactory> */
    use HasFactory, HasUlids, InteractsWithMedia, \OwenIt\Auditing\Auditable, SoftDeletes;

    public const string IMAGE_COLLECTION = 'tour-images';

    public const int MAX_IMAGES = 10;

    public const int MAX_SUPPORTING_GUIDES = 4;

    public function leadGuide(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_guide_id');
    }

    public function guides(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'guide_tour')
            ->orderBy('name')
            ->orderBy('users.id');
    }

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

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function upcomingStartDates(): HasMany
    {
        return $this->hasMany(TourStartDate::class)
            ->where('start_datetime_utc', '>', now('UTC'))
            ->where('is_active', true)
            ->orderBy('start_datetime_utc');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::IMAGE_COLLECTION)
            ->useDisk((string) config('media-library.disk_name', 'r2'))
            ->acceptsMimeTypes([
                'image/jpeg',
                'image/png',
                'image/webp',
            ]);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('card')
            ->fit(Fit::Crop, 1200, 800)
            ->format('webp')
            ->performOnCollections(self::IMAGE_COLLECTION)
            ->nonQueued();

        $this->addMediaConversion('thumbnail')
            ->fit(Fit::Crop, 480, 320)
            ->format('webp')
            ->performOnCollections(self::IMAGE_COLLECTION)
            ->nonQueued();
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
     * Get the tour's ordered image payload.
     *
     * @return Attribute<list<array{id: int, position: int, is_cover: bool, name: string, mime_type: string|null, size_bytes: int, original_url: string, card_url: string, thumbnail_url: string}>, never>
     */
    protected function images(): Attribute
    {
        return Attribute::make(
            get: fn (): array => $this->getMedia(self::IMAGE_COLLECTION)
                ->values()
                ->map(
                    fn (Media $media, int $index): array => TourImageData::fromMedia($media, $index + 1)->toArray(),
                )
                ->all(),
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
