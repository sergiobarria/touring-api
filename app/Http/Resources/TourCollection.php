<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class TourCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'results' => $this->collection->count(),
            'data' => $this->collection,
            'links' => [
                // 'self' => 'link-value', // Example of how to add metadata
            ],
            'meta' => [
                'results' => $this->collection->count(),
            ],
        ];
    }
}
