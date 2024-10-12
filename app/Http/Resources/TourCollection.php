<?php

namespace App\Http\Resources;

use App\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class TourCollection extends ResourceCollection
{
    protected array $withoutFields = ['description']; // Fields that should be excluded by default from the response
    protected array $dynamicFields = ['start_dates']; // Fields that are not part of the model (e.g. relationships)

    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->collection->map(function ($tour) use ($request) {
            $resource = new TourResource($tour);

            $data = $resource->toArray($request);
            // Remove the default excluded fields from the response
            $data = array_diff_key($data, array_flip($this->withoutFields));

            foreach ($this->dynamicFields as $field) {
                if (!$request->has('include') || !in_array($field, explode(',', $request->include))) {
                    unset($data[$field]);
                }
            }

            // Handle the fields query param to return only the requested fields
            if ($request->has('fields')) {
                $fields = explode(',', $request->fields);
                $data = array_intersect_key($data, array_flip($fields));
            }

            return $data;
        });

        return [
            'results' => $this->collection->count(),
            'data' => $data,
            'links' => [
                // 'self' => 'link-value', // Example of how to add metadata
            ],
            'meta' => [
                'results' => $this->collection->count(),
            ],
        ];
    }
}
