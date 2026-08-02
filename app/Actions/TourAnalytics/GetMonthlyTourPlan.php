<?php

namespace App\Actions\TourAnalytics;

use App\Models\TourStartDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final readonly class GetMonthlyTourPlan
{
    /** @return list<array{month: int, num_tour_starts: int, tours: list<string>}> */
    public function handle(int $year): array
    {
        $yearStart = CarbonImmutable::create($year, 1, 1, 0, 0, 0, 'UTC');
        $yearEnd = $yearStart->endOfYear();

        return TourStartDate::query()
            ->select(['id', 'tour_id', 'start_datetime_utc'])
            ->with('tour:id,name')
            ->where('is_active', true)
            ->whereBetween('start_datetime_utc', [$yearStart, $yearEnd])
            ->whereHas('tour', fn (Builder $query): Builder => $query->where('is_active', true))
            ->orderBy('start_datetime_utc')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (TourStartDate $startDate): int => $startDate->start_datetime_utc->month)
            ->map(fn (Collection $departures, int $month): array => [
                'month' => $month,
                'num_tour_starts' => $departures->count(),
                'tours' => $departures
                    ->map(fn (TourStartDate $startDate): string => $startDate->tour->name)
                    ->all(),
            ])
            ->sort(function (array $left, array $right): int {
                return ($right['num_tour_starts'] <=> $left['num_tour_starts'])
                    ?: ($left['month'] <=> $right['month']);
            })
            ->values()
            ->all();
    }
}
