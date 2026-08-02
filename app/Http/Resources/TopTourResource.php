<?php

namespace App\Http\Resources;

use App\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/** @mixin Tour */
class TopTourResource extends JsonApiResource
{
    /**
     * The resource's attributes.
     */
    public array $attributes = [
        'name',
        'price',
        'rating_avg',
        'summary',
        'difficulty',
    ];

    public function __construct(mixed $resource)
    {
        parent::__construct($resource);

        $this->ignoreFieldsAndIncludesInQueryString();
    }

    public function toType(Request $request): string
    {
        return 'tours';
    }
}
