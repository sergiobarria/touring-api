<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TourResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'price' => number_format($this->price, 2),
            'duration' => $this->duration,
            'duration_in_weeks' => $this->durationInWeeks,
            'max_group_size' => $this->max_group_size,
            'rating' => $this->rating,
            'ratings_quantity' => $this->ratings_quantity,
            'summary' => $this->summary,
            'description' => $this->description,
            'start_dates' => $this->when(
                $request->has('include') && in_array('start_dates', explode(',', $request->include)),
                $this->startDates->pluck('start_date')->toArray()
            ),
        ];

        if ($request->has('fields')) {
            $fields = explode(',', $request->fields);
            $data = array_intersect_key($data, array_flip($fields));
        }

        return $data;
    }
}
