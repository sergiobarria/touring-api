<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Tour extends Model implements Auditable
{
    use HasUlids, HasFactory, HasSlug, \OwenIt\Auditing\Auditable;

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

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
