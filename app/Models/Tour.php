<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Tour extends Model implements Auditable, HasMedia
{
    use HasUlids, HasFactory, HasSlug, InteractsWithMedia, \OwenIt\Auditing\Auditable;

    public const ALLOWED_SELECT_FIELDS = [
        'id', 'name', 'slug', 'max_group_size', 'duration_days', 'price', 'max_group_size',
        'price_discount_percent', 'summary', 'description', 'rating_avg', 'rating_count'
    ];
    public const ALLOWED_INCLUDES = ['dates'];
    public const ALLOWED_SORTS = ['name', 'price', 'max_group_size', 'duration_days', 'created_at'];
    public const DIFFICULTY_ENUM = ['easy', 'moderate', 'difficult'];
    protected $fillable = [
        'name', 'duration_days', 'max_group_size', 'difficulty', 'rating_avg', 'rating_count',
        'price', 'price_discount_percent', 'summary', 'description', 'is_active',
    ];

    public function getDurationWeeksAttribute(): int
    {
        return round($this->duration_days / 7, 1);
    }

    public function getImagesUrlsAttribute(): array
    {
        return $this->getMedia('tours')->map(fn($media) => $media->getUrl())->toArray();
    }

    public function getUpcomingDatesAttribute(): array
    {
        return $this->dates()
            ->where('start_datetime_utc', '>', now())
            ->where('is_active', true)
            ->orderBy('start_datetime_utc')
            ->get()
            ->pluck('start_datetime_utc')
            ->map(fn(CarbonImmutable $date) => $date->toIso8601String())
            ->toArray();
    }

    public function dates(): HasMany
    {
        return $this->hasMany(TourDate::class);
    }

    public function scopeMinPrice($query, $price)
    {
        return $query->where('price', '>=', $price);
    }

    public function scopeMaxPrice($query, $price)
    {
        return $query->where('price', '<=', $price);
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('preview')
            ->fit(Fit::Contain, 300, 300)
            ->nonQueued();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('tours')
            ->useDisk('r2');
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
