<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateTourRequest;
use App\Http\Requests\ToursListRequest;
use App\Http\Requests\UpdateTourRequest;
use App\Http\Resources\TourCollection;
use App\Http\Resources\TourResource;
use App\Models\Tour;
use App\Services\TourService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TourController extends Controller
{


    function __construct(protected TourService $tourService)
    {
        // ...
    }

    public function index(ToursListRequest $request): TourCollection
    {
        $tours = $this->tourService->getTours($request->validated(), explode(',', $request->input('include', [])));
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
        $tour = $this->tourService->createTour($request);
        if (is_null($tour)) {
            return response()->json(['error' => 'Failed to create tour.'], 500);
        }

        return new TourResource($tour);
    }

    public function update(UpdateTourRequest $request, Tour $tour): TourResource|JsonResponse
    {
        $tour = $this->tourService->updateTour($tour, $request);
        if (is_null($tour)) {
            return response()->json(['error' => 'Failed to update tour.'], 500);
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
        $tours = $this->tourService->getTopRatedTours();
        return new TourCollection($tours);
    }

    public function getTourStats(): JsonResponse
    {
        $stats = $this->tourService->getTourStats();
        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }

    public function getMonthlyPlan(int $year): JsonResponse
    {
        $plan = $this->tourService->getMonthlyPlan($year);
        return response()->json([
            'status' => 'success',
            'data' => $plan,
        ]);
    }
}
