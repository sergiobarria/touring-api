<?php

namespace App\Services\Tours;

use App\Models\Tour;

final readonly class TourRatingService
{
    public function recompute(Tour $tour): void
    {
        $aggregate = $tour->reviews()
            ->selectRaw('COUNT(*) as rating_count, AVG(rating) as rating_avg')
            ->first();
        $count = (int) $aggregate->getAttribute('rating_count');

        $tour->forceFill([
            'rating_count' => $count,
            'rating_avg' => $count === 0 ? null : round((float) $aggregate->getAttribute('rating_avg'), 2),
        ])->save();
    }
}
