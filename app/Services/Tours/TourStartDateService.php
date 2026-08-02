<?php

namespace App\Services\Tours;

use App\Models\Tour;
use App\Models\TourStartDate;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

final readonly class TourStartDateService
{
    public function findOwned(Tour $tour, string $startDateId): TourStartDate
    {
        return $tour->startDates()->findOrFail($startDateId);
    }

    public function ensureCapacity(Tour $tour, int $availableSpots): void
    {
        if ($availableSpots > $tour->max_group_size) {
            throw ValidationException::withMessages([
                'available_spots' => 'The available spots field must not be greater than the tour maximum group size.',
            ]);
        }
    }

    public function throwDuplicateValidationException(QueryException $exception): never
    {
        if (in_array($exception->getCode(), ['23000', '23505'], strict: true)) {
            throw ValidationException::withMessages([
                'start_datetime_utc' => 'The tour already has a start date at this instant.',
            ]);
        }

        throw $exception;
    }
}
