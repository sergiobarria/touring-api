<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Tour extends Model implements Auditable
{
    use HasFactory, HasSlug, HasUuids, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'name',
        'price',
        'duration',
        'max_group_size',
        'difficulty',
        'rating',
        'ratings_quantity',
        'summary',
        'description',
        'is_public'
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    public function startDates(): HasMany
    {
        return $this->hasMany(StartDate::class);
    }

    public function price(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value / 100,
            set: fn($value) => $value * 100
        );
    }

    public function durationInWeeks(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => round($attributes['duration'] / 7, 1),
        );
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }
}
