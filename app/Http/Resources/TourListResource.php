<?php

namespace App\Http\Resources;

use App\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/** @mixin Tour */
class TourListResource extends JsonApiResource
{
    /**
     * The resource's attributes.
     */
    public array $attributes = [
        'name',
        'slug',
        'max_group_size',
        'duration_days',
        'duration_weeks',
        'upcoming_dates',
        'difficulty',
        'price',
        'price_discount_percent',
        'summary',
        'rating_avg',
        'rating_count',
    ];

    /**
     * The resource's relationships.
     */
    public array $relationships = [
        'startDates' => TourStartDateResource::class,
    ];

    public function toType(Request $request): string
    {
        return 'tours';
    }
}
