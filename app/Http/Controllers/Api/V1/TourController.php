<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateTourRequest;
use App\Http\Requests\ToursListRequest;
use App\Http\Requests\UpdateTourRequest;
use App\Http\Resources\TourCollection;
use App\Http\Resources\TourResource;
use App\Models\Tour;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TourController extends Controller
{


    function __construct()
    {
        // ...
    }

    public function index(ToursListRequest $request): TourCollection
    {
        $tours = Tour::where('is_public', true)
            ->when($request->priceFrom, fn($query) => $query->where('price', '>=', $request->priceFrom * 100))
            ->when($request->priceTo, fn($query) => $query->where('price', '<=', $request->priceTo * 100))
            ->when($request->sortBy && $request->sortOrder, fn($query) => $query->orderBy($request->sortBy, $request->sortOrder))
            ->when($request->has('include') && in_array('start_dates', explode(',', $request->include)), fn($query) => $query->with('startDates'))
            ->orderBy('created_at')
            ->paginate(10);

        return new TourCollection($tours);
    }

    public function show(Request $request, Tour $tour): TourResource
    {
        if ($request->has('include') && in_array('start_dates', explode(',', $request->include))) {
            $tour->load('startDates');
        }

        return new TourResource($tour);
    }

    public function getBySlug(Request $request, string $slug): TourResource
    {
        $tour = Tour::where('slug', $slug)->firstOrFail();

        if ($request->has('include') && in_array('start_dates', explode(',', $request->include))) {
            $tour->load('startDates');
        }

        return new TourResource($tour);
    }

    public function store(CreateTourRequest $request): TourResource|JsonResponse
    {
        try {
            $tour = DB::transaction(function () use ($request) {
                $tour = Tour::create($request->validated());

                if ($request->has('start_dates')) {
                    $this->syncStartDates($tour, $request->start_dates);
                    $tour->load('startDates');
                }

                return $tour;
            });

            return new TourResource($tour);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(UpdateTourRequest $request, Tour $tour): TourResource|JsonResponse
    {
        try {
            $tour = DB::transaction(function () use ($request, $tour) {
                $tour->update($request->validated());

                if ($request->has('start_dates')) {
                    $this->syncStartDates($tour, $request->start_dates);
                    $tour->load('startDates');
                }

                return $tour;
            });
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return new TourResource($tour);
    }

    public function destroy(Tour $tour): JsonResponse
    {
        $tour->delete();

        return response()->json([], 204);
    }

    public function getTopRatedTours(): TourCollection
    {
        $tours = Tour::where('is_public', true)
            ->orderByDesc('rating')
            ->take(5)
            ->get();

        return new TourCollection($tours);
    }

    public function getTourStats(): JsonResponse
    {
        $stats = Tour::where('is_public', true)
            ->where('rating', '>=', 1)
            ->selectRaw('UPPER(difficulty) as difficulty')
            ->selectRaw('COUNT(*) as numTours')
            ->selectRaw('SUM(ratings_quantity) as numRatings')
            ->selectRaw('ROUND(AVG(rating), 2) as avgRating')
            ->selectRaw('ROUND(AVG(price), 2) as avgPrice')
            ->selectRaw('MIN(price) as minPrice')
            ->selectRaw('MAX(price) as maxPrice')
            ->groupBy('difficulty')
            ->orderBy('avgPrice', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }

    public function getMonthlyPlan(int $year): JsonResponse
    {
        $plan = Tour::where('is_public', true)
            ->join('start_dates', 'start_dates.tour_id', '=', 'tours.id')
            ->selectRaw('TO_CHAR(start_dates.start_date, \'FMMonth\') as month')
            ->selectRaw('COUNT(*) as numTourStarts')
            ->selectRaw('json_agg(tours.name) as tours')
            ->whereBetween('start_dates.start_date', [
                Carbon::create($year, 1, 1)->startOfDay(),
                Carbon::create($year, 12, 31)->endOfDay(),
            ])
            ->groupBy('month')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(12)
            ->get();

        $plan->each(function ($item) {
            $item->tours = json_decode($item->tours, true);
        });

        return response()->json([
            'status' => 'success',
            'data' => $plan,
        ]);
    }

    protected function syncStartDates(Tour $tour, array $startDates): void
    {
        $tour->startDates()->delete();

        foreach ($startDates as $startDate) {
            $tour->startDates()->create(['start_date' => $startDate]);
        }
    }
}
