<?php

namespace App\Http\Resources;

use App\DataTransferObjects\TourImageData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Illuminate\Support\Arr;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** @mixin Media */
class TourImageResource extends JsonApiResource
{
    /**
     * The resource's attributes.
     *
     * @return array{
     *     position: int,
     *     is_cover: bool,
     *     name: string,
     *     mime_type: string|null,
     *     size_bytes: int,
     *     original_url: string,
     *     card_url: string,
     *     thumbnail_url: string
     * }
     */
    public function toAttributes(Request $request): array
    {
        return Arr::except(
            TourImageData::fromMedia($this->resource, (int) $this->resource->order_column)->toArray(),
            'id',
        );
    }

    public function toType(Request $request): string
    {
        return 'tour_images';
    }
}
