<?php

namespace App\Http\Resources;

use App\Models\TourStartDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/** @mixin TourStartDate */
class TourStartDateResource extends JsonApiResource
{
    /**
     * The resource's attributes.
     */
    public array $attributes = [
        'start_datetime_utc',
        'available_spots',
        'is_active',
    ];

    public function toType(Request $request): string
    {
        return 'tour_start_dates';
    }
}
