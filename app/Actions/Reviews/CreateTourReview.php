<?php

namespace App\Actions\Reviews;

use App\DataTransferObjects\ReviewData;
use App\Enums\BookingStatus;
use App\Models\Review;
use App\Models\Tour;
use App\Models\User;
use App\Services\Tours\TourRatingService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class CreateTourReview
{
    public function __construct(private TourRatingService $ratings) {}

    /** @throws Throwable */
    public function handle(Tour $tour, User $user, ReviewData $data): Review
    {
        try {
            return DB::transaction(function () use ($tour, $user, $data): Review {
                $lockedTour = Tour::query()->lockForUpdate()->findOrFail($tour->getKey());
                if (! $user->bookings()->whereBelongsTo($lockedTour)
                    ->where('status', BookingStatus::CONFIRMED)
                    ->where('departure_datetime_utc', '<', now('UTC'))->exists()) {
                    throw ValidationException::withMessages([
                        'review' => 'You can review a tour only after completing a booked departure.',
                    ]);
                }
                $review = $lockedTour->reviews()->create([
                    ...$data->toArray(),
                    'user_id' => $user->getKey(),
                ]);
                $this->ratings->recompute($lockedTour);

                return $review->load('user:id,name');
            });
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'reviews_tour_id_user_id_unique')
                || str_contains($exception->getMessage(), 'reviews.tour_id, reviews.user_id')) {
                throw ValidationException::withMessages([
                    'review' => 'You have already reviewed this tour.',
                ]);
            }

            throw $exception;
        }
    }
}
