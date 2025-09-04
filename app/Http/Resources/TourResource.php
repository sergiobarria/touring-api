<?php

namespace App\Http\Resources;

use App\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tour
 */
class TourResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'duration' => [
                'days' => $this->duration_days,
                'weeks' => $this->duration_weeks,
            ],
            'max_group_size' => $this->max_group_size,
            'difficulty' => $this->difficulty,
            'price' => [
                'amount' => $this->price,
                'discount_percent' => $this->price_discount_percent,
                'discounted_amount' => $this->price_discount_percent
                    ? round($this->price * (1 - $this->price_discount_percent / 100), 2)
                    : null,
            ],
            'rating' => [
                'average' => $this->rating_avg,
                'count' => $this->rating_count,
            ],
            'summary' => $this->summary,
            'description' => $this->description,
            'schedules' => TourScheduleResource::collection($this->whenLoaded('schedules'))
        ];
    }
}
