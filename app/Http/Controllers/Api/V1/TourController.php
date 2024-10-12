<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ToursListRequest;
use App\Http\Resources\TourCollection;
use App\Http\Resources\TourResource;
use App\Http\Services\TourService;
use App\Models\Tour;
use Illuminate\Http\JsonResponse;

class TourController extends Controller
{


    function __construct(protected TourService $tourService)
    {
        // ...
    }

    public function index(ToursListRequest $request): TourCollection
    {
        $tours = $this->tourService->getTourList($request);

        return new TourCollection($tours);
    }

    public function show(Tour $tour): TourResource
    {
        return new TourResource($tour);
    }

    public function getBySlug(string $slug): TourResource
    {
        $tour = Tour::where('slug', $slug)->firstOrFail();

        return new TourResource($tour);
    }

    public function store(): JsonResponse
    {
        return response()->json(['message' => 'Store method, not implemented yet.'], 501);
    }

    public function update(): JsonResponse
    {
        return response()->json(['message' => 'Update method, not implemented yet.'], 501);
    }

    public function destroy(Tour $tour): JsonResponse
    {
        $tour->delete();

        return response()->json([], 204);
    }
}
