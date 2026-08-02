<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTourStartDateRequest;
use App\Http\Requests\Api\V1\TourStartDateListRequest;
use App\Http\Requests\Api\V1\UpdateTourStartDateRequest;
use App\Http\Resources\TourStartDateResource;
use App\Models\Tour;
use App\Models\TourStartDate;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Header;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

#[Group('Tour Start Dates')]
class TourStartDateController extends Controller
{
    /**
     * List a tour's start dates.
     *
     * Return all non-deleted historical and future start dates in chronological order.
     */
    public function index(TourStartDateListRequest $request, Tour $tour): AnonymousResourceCollection
    {
        $startDates = $tour->startDates()
            ->orderBy('start_datetime_utc')
            ->orderBy('id')
            ->paginate(
                perPage: $request->integer('per_page', 15),
                page: $request->integer('page', 1),
            )
            ->withQueryString();

        return TourStartDateResource::collection($startDates);
    }

    /**
     * Create a tour start date.
     *
     * Schedule a historical or future departure. Datetimes are normalized to UTC.
     */
    #[Response(
        201,
        description: 'Tour start date created.',
        mediaType: 'application/vnd.api+json',
        type: TourStartDateResource::class,
    )]
    #[Header(
        'Location',
        description: 'URL of the created tour start date.',
        type: 'string',
        format: 'uri',
        required: true,
        status: 201,
    )]
    public function store(StoreTourStartDateRequest $request, Tour $tour): JsonResponse
    {
        try {
            $startDate = DB::transaction(function () use ($request, $tour): TourStartDate {
                $lockedTour = Tour::query()->lockForUpdate()->findOrFail($tour->getKey());
                $attributes = $request->toDto()->toArray();

                $this->ensureCapacity($lockedTour, $attributes['available_spots']);

                return $lockedTour->startDates()->create($attributes);
            });
        } catch (QueryException $exception) {
            $this->throwDuplicateValidationException($exception);
        }

        return TourStartDateResource::make($startDate)
            ->response()
            ->setStatusCode(201)
            ->header('Location', route('v1.tours.start-dates.show', [$tour, $startDate]));
    }

    /**
     * Show a tour start date.
     *
     * Retrieve a non-deleted start date that belongs to the given tour.
     */
    #[Response(404, description: 'Tour or start date not found.', type: 'array{message: string}')]
    public function show(Tour $tour, string $tourStartDate): TourStartDateResource
    {
        return TourStartDateResource::make($this->findStartDate($tour, $tourStartDate));
    }

    /**
     * Update a tour start date.
     *
     * Partially update a historical or future departure.
     */
    #[Response(404, description: 'Tour or start date not found.', type: 'array{message: string}')]
    public function update(
        UpdateTourStartDateRequest $request,
        Tour $tour,
        string $tourStartDate,
    ): TourStartDateResource {
        $startDate = $this->findStartDate($tour, $tourStartDate);

        try {
            $startDate = DB::transaction(function () use ($request, $tour, $startDate): TourStartDate {
                $lockedTour = Tour::query()->lockForUpdate()->findOrFail($tour->getKey());
                $lockedStartDate = $lockedTour->startDates()->lockForUpdate()->findOrFail($startDate->getKey());
                $attributes = $request->toDto($lockedStartDate)->toArray();

                $this->ensureCapacity($lockedTour, $attributes['available_spots']);
                $lockedStartDate->update($attributes);

                return $lockedStartDate->refresh();
            });
        } catch (QueryException $exception) {
            $this->throwDuplicateValidationException($exception);
        }

        return TourStartDateResource::make($startDate);
    }

    /**
     * Delete a tour start date.
     *
     * Soft-delete a historical or future departure.
     */
    #[Response(404, description: 'Tour or start date not found.', type: 'array{message: string}')]
    public function destroy(Tour $tour, string $tourStartDate): HttpResponse
    {
        $this->findStartDate($tour, $tourStartDate)->delete();

        return response()->noContent();
    }

    private function findStartDate(Tour $tour, string $tourStartDate): TourStartDate
    {
        return $tour->startDates()->findOrFail($tourStartDate);
    }

    private function throwDuplicateValidationException(QueryException $exception): never
    {
        if (in_array($exception->getCode(), ['23000', '23505'], strict: true)) {
            throw ValidationException::withMessages([
                'start_datetime_utc' => 'The tour already has a start date at this instant.',
            ]);
        }

        throw $exception;
    }

    private function ensureCapacity(Tour $tour, int $availableSpots): void
    {
        if ($availableSpots > $tour->max_group_size) {
            throw ValidationException::withMessages([
                'available_spots' => 'The available spots field must not be greater than the tour maximum group size.',
            ]);
        }
    }
}
