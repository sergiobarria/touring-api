<?php

namespace App\Actions\TourStartDates;

use App\Models\Tour;
use App\Models\TourStartDate;
use App\Services\Tours\TourStartDateService;

final readonly class ShowTourStartDate
{
    public function __construct(private TourStartDateService $startDates) {}

    public function handle(Tour $tour, string $startDateId): TourStartDate
    {
        return $this->startDates->findOwned($tour, $startDateId);
    }
}
