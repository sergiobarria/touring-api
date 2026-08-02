<?php

namespace App\Actions\Reviews;

use App\Models\Review;
use App\Models\Tour;

final readonly class ShowTourReview
{
    public function handle(Tour $tour, string $reviewId): Review
    {
        return $tour->reviews()->with('user:id,name')->findOrFail($reviewId);
    }
}
