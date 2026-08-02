<?php

namespace App\Actions\TourStartDates;

use App\Http\Requests\Api\V1\UpdateTourStartDateRequest;
use App\Models\Tour;
use App\Models\TourStartDate;
use App\Services\Tours\TourStartDateService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class UpdateTourStartDate
{
    public function __construct(private TourStartDateService $startDates) {}

    /** @throws Throwable */
    public function handle(Tour $tour, string $startDateId, UpdateTourStartDateRequest $request): TourStartDate
    {
        $this->startDates->findOwned($tour, $startDateId);

        try {
            return DB::transaction(function () use ($tour, $startDateId, $request): TourStartDate {
                $tour = Tour::query()->lockForUpdate()->findOrFail($tour->getKey());
                $startDate = $tour->startDates()->lockForUpdate()->findOrFail($startDateId);
                $attributes = $request->toDto($startDate)->toArray();
                $this->startDates->ensureCapacity($tour, $attributes['available_spots']);
                $startDate->update($attributes);

                return $startDate->refresh();
            });
        } catch (QueryException $exception) {
            $this->startDates->throwDuplicateValidationException($exception);
        }
    }
}
