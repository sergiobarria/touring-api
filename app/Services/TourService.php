<?php

namespace App\Services;

use App\Http\Requests\CreateTourRequest;
use App\Http\Requests\UpdateTourRequest;
use App\Models\Tour;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Log;
use Throwable;

class TourService
{
    public function getTours(array $filters, array $includes = []): LengthAwarePaginator
    {
        return Tour::where('is_public', true)
            ->when($filters['priceFrom'] ?? null, fn($query) => $query->where('price', '>=', $filters['priceFrom'] * 100))
            ->when($filters['priceTo'] ?? null, fn($query) => $query->where('price', '<=', $filters['priceTo'] * 100))
            ->when(!empty($filters['sortBy']) && !empty($filters['sortOrder']), fn($query) => $query->orderBy($filters['sortBy'], $filters['sortOrder']))
            ->when(in_array('start_dates', $includes), fn($query) => $query->with('startDates'))
            ->orderBy('created_at')
            ->paginate($filters['limit'] ?? 10);
    }

    public function syncStartDates(Tour $tour, array $startDates): void
    {
        $tour->startDates()->delete();

        foreach ($startDates as $startDate) {
            $tour->startDates()->create(['start_date' => $startDate]);
        }
    }

    public function createTour(CreateTourRequest $request): ?Tour
    {
        try {
            return DB::transaction(function () use ($request) {
                $tour = Tour::create($request->validated());

                if ($request->has('start_dates')) {
                    $this->syncStartDates($tour, $request->input('start_dates'));
                    $tour->load('startDates');
                }

                return $tour;
            });
        } catch (Throwable $e) {
            Log::error('💥Error creating tour: ' . $e->getMessage());
            return null;
        }
    }

    public function updateTour(Tour $tour, UpdateTourRequest $request): ?Tour
    {
        try {
            return DB::transaction(function () use ($tour, $request) {
                $tour->update($request->validated());

                if ($request->has('start_dates')) {
                    $this->syncStartDates($tour, $request->input('start_dates'));
                    $tour->load('startDates');
                }

                return $tour;
            });
        } catch (Throwable $e) {
            Log::error('💥Error updating tour: ' . $e->getMessage());
            return null;
        }
    }

    public function getTopRatedTours(): Collection
    {
        return Tour::where('is_public', true)
            ->orderByDesc('rating')
            ->take(5)
            ->get();
    }

    public function getTourStats(): Collection
    {
        return Tour::where('is_public', true)
            ->where('rating', '>=', 1)
            ->selectRaw('UPPER(difficulty) as difficulty')
            ->selectRaw('COUNT(*) as numTours')
            ->selectRaw('SUM(ratings_quantity) as numRatings')
            ->selectRaw('ROUND(CAST(AVG(rating) as NUMERIC), 2) as avgRating')
            ->selectRaw('ROUND(CAST(AVG(price) as NUMERIC), 2) as avgPrice')
            ->selectRaw('MIN(price) as minPrice')
            ->selectRaw('MAX(price) as maxPrice')
            ->groupBy('difficulty')
            ->orderByRaw('ROUND(CAST(AVG(price) as NUMERIC), 2) asc')
            ->get();
    }

    public function getMonthlyPlan(int $year): Collection
    {
        $plan = Tour::where('is_public', true)
            ->join('start_dates', 'start_dates.tour_id', '=', 'tours.id')
            ->selectRaw('TO_CHAR(start_dates.start_date, \'FMMonth\') as month')
            ->selectRaw('COUNT(*) as numTourStarts')
            ->selectRaw('json_agg(tours.name) as tours')
            ->whereBetween('start_dates.start_date', [
                Carbon::create($year)->startOfDay(),
                Carbon::create($year, 12, 31)->endOfDay(),
            ])
            ->groupBy('month')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(12)
            ->get();

        $plan->each(function ($item) {
            $item->tours = json_decode($item->tours, true);
        });

        return $plan;
    }
}
