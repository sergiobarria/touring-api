<?php

namespace App\Actions\TourStartDates;

use App\Models\Tour;
use App\Models\TourStartDate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ListTourStartDates
{
    /** @return LengthAwarePaginator<int, TourStartDate> */
    public function handle(Tour $tour, int $perPage, int $page): LengthAwarePaginator
    {
        return $tour->startDates()
            ->orderBy('start_datetime_utc')
            ->orderBy('id')
            ->paginate(perPage: $perPage, page: $page)
            ->withQueryString();
    }
}
