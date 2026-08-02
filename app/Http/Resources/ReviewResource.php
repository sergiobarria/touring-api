<?php

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/** @mixin Review */
class ReviewResource extends JsonApiResource
{
    public array $attributes = ['rating', 'review', 'created_at', 'updated_at'];

    public function toAttributes(Request $request): array
    {
        return [
            'rating' => $this->rating,
            'review' => $this->review,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'author' => ['id' => $this->user->id, 'name' => $this->user->name],
        ];
    }

    public function toType(Request $request): string
    {
        return 'reviews';
    }
}
