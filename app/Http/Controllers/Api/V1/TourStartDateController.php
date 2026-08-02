<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\TourStartDates\CreateTourStartDate;
use App\Actions\TourStartDates\DeleteTourStartDate;
use App\Actions\TourStartDates\ListTourStartDates;
use App\Actions\TourStartDates\ShowTourStartDate;
use App\Actions\TourStartDates\UpdateTourStartDate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTourStartDateRequest;
use App\Http\Requests\Api\V1\TourStartDateListRequest;
use App\Http\Requests\Api\V1\UpdateTourStartDateRequest;
use App\Http\Resources\TourStartDateResource;
use App\Models\Tour;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Header;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;
use Throwable;

#[Group('Tour Start Dates')]
class TourStartDateController extends Controller
{
    /**
     * List a tour's start dates.
     *
     * Return all non-deleted historical and future start dates in chronological order.
     */
    public function index(
        TourStartDateListRequest $request,
        Tour $tour,
        ListTourStartDates $action,
    ): AnonymousResourceCollection {
        return TourStartDateResource::collection($action->handle(
            tour: $tour,
            perPage: $request->integer('per_page', 15),
            page: $request->integer('page', 1),
        ));
    }

    /**
     * Create a tour start date.
     *
     * Schedule a historical or future departure. Datetimes are normalized to UTC.
     *
     * @throws Throwable
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
    public function store(
        StoreTourStartDateRequest $request,
        Tour $tour,
        CreateTourStartDate $action,
    ): JsonResponse {
        $startDate = $action->handle($tour, $request->toDto());

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
    public function show(
        Tour $tour,
        string $tourStartDate,
        ShowTourStartDate $action,
    ): TourStartDateResource {
        return TourStartDateResource::make($action->handle($tour, $tourStartDate));
    }

    /**
     * Update a tour start date.
     *
     * Partially update a historical or future departure.
     *
     * @throws Throwable
     */
    #[Response(404, description: 'Tour or start date not found.', type: 'array{message: string}')]
    public function update(
        UpdateTourStartDateRequest $request,
        Tour $tour,
        string $tourStartDate,
        UpdateTourStartDate $action,
    ): TourStartDateResource {
        return TourStartDateResource::make($action->handle($tour, $tourStartDate, $request));
    }

    /**
     * Delete a tour start date.
     *
     * Soft-delete a historical or future departure.
     */
    #[Response(404, description: 'Tour or start date not found.', type: 'array{message: string}')]
    public function destroy(
        Tour $tour,
        string $tourStartDate,
        DeleteTourStartDate $action,
    ): HttpResponse {
        $action->handle($tour, $tourStartDate);

        return response()->noContent();
    }
}
