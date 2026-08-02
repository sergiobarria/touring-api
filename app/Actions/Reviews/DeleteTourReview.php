<?php

namespace App\Actions\Reviews;

use App\Models\Review;
use App\Models\Tour;
use App\Services\Tours\TourRatingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

final readonly class DeleteTourReview
{
    public function __construct(private TourRatingService $ratings) {}

    /** @throws Throwable */
    public function handle(Tour $tour, string $reviewId): void
    {
        $review = $tour->reviews()->findOrFail($reviewId);
        Gate::authorize('delete', $review);

        DB::transaction(function () use ($tour, $review): void {
            $lockedTour = Tour::query()->lockForUpdate()->findOrFail($tour->getKey());
            Review::query()->whereKey($review->getKey())->delete();
            $this->ratings->recompute($lockedTour);
        });
    }
}
