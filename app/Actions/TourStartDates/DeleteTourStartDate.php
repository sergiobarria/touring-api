<?php

namespace App\Actions\TourStartDates;

use App\Models\Tour;
use App\Services\Tours\TourStartDateService;

final readonly class DeleteTourStartDate
{
    public function __construct(private TourStartDateService $startDates) {}

    public function handle(Tour $tour, string $startDateId): void
    {
        $this->startDates->findOwned($tour, $startDateId)->delete();
    }
}
