<?php

namespace App\Http\Resources;

use App\Models\TourSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TourSchedule
 */
class TourScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'start_datetime_utc' => $this->start_datetime_utc->toISOString(),
            'is_active' => $this->is_active,
            'available_spots' => $this->available_spots
        ];
    }
}
