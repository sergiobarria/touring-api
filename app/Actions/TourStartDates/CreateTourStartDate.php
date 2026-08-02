<?php

namespace App\Actions\TourStartDates;

use App\DataTransferObjects\TourStartDateData;
use App\Models\Tour;
use App\Models\TourStartDate;
use App\Services\Tours\TourStartDateService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class CreateTourStartDate
{
    public function __construct(private TourStartDateService $startDates) {}

    /** @throws Throwable */
    public function handle(Tour $tour, TourStartDateData $data): TourStartDate
    {
        try {
            return DB::transaction(function () use ($tour, $data): TourStartDate {
                $tour = Tour::query()->lockForUpdate()->findOrFail($tour->getKey());
                $attributes = $data->toArray();
                $this->startDates->ensureCapacity($tour, $attributes['available_spots']);

                return $tour->startDates()->create($attributes);
            });
        } catch (QueryException $exception) {
            $this->startDates->throwDuplicateValidationException($exception);
        }
    }
}
