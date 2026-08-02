<?php

namespace App\Actions\Reviews;

use App\DataTransferObjects\ReviewData;
use App\Models\Review;
use App\Models\Tour;
use App\Services\Tours\TourRatingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

final readonly class UpdateTourReview
{
    public function __construct(private TourRatingService $ratings) {}

    /** @param array<string, mixed> $attributes */
    /** @throws Throwable */
    public function handle(Tour $tour, string $reviewId, array $attributes): Review
    {
        $review = $tour->reviews()->findOrFail($reviewId);
        Gate::authorize('update', $review);

        return DB::transaction(function () use ($tour, $review, $attributes): Review {
            $lockedTour = Tour::query()->lockForUpdate()->findOrFail($tour->getKey());
            $lockedReview = Review::query()->lockForUpdate()->findOrFail($review->getKey());
            $lockedReview->update(ReviewData::forUpdate($lockedReview, $attributes)->toArray());
            $this->ratings->recompute($lockedTour);

            return $lockedReview->load('user:id,name');
        });
    }
}
