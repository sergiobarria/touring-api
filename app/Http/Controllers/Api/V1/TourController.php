<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tours\CreateTour;
use App\Actions\Tours\DeleteTour;
use App\Actions\Tours\ListTours;
use App\Actions\Tours\ShowTour;
use App\Actions\Tours\UpdateTour;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTourRequest;
use App\Http\Requests\Api\V1\TourListRequest;
use App\Http\Requests\Api\V1\UpdateTourRequest;
use App\Http\Resources\TourListResource;
use App\Http\Resources\TourResource;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Header;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;
use Throwable;

#[Group('Tours')]
class TourController extends Controller
{
    /**
     * List all tours.
     *
     * Browse the active tours catalog with sparse fields and optional start dates.
     */
    #[QueryParameter(
        'sort',
        description: 'Comma-separated sort fields. Prefix a field with "-" for descending order. Allowed fields: name, price, max_group_size, duration_days, created_at.',
        type: 'string',
        example: '-price,name',
    )]
    public function index(TourListRequest $request, ListTours $action): AnonymousResourceCollection
    {
        return TourListResource::collection($action->handle(
            perPage: $request->integer('per_page', 15),
            page: $request->integer('page', 1),
        ));
    }

    /**
     * Create a tour.
     *
     * Create a tour from validated catalog data. Slugs and ratings are server-managed.
     *
     * @throws Throwable
     */
    #[Response(
        201,
        description: 'Tour created.',
        mediaType: 'application/vnd.api+json',
        type: TourResource::class,
    )]
    #[Header(
        'Location',
        description: 'URL of the created tour.',
        type: 'string',
        format: 'uri',
        required: true,
        status: 201,
    )]
    public function store(StoreTourRequest $request, CreateTour $action): JsonResponse
    {
        $tour = $action->handle($request->toDto(), $request->validated('guide_ids', []));

        return TourResource::make($tour)
            ->response()
            ->setStatusCode(201)
            ->header('Location', route('v1.tours.show', $tour));
    }

    /**
     * Show an active tour.
     *
     * Retrieve a tour by ULID with optional start dates and sparse fields.
     */
    #[Response(404, description: 'Tour not found.', type: 'array{message: string}')]
    public function show(string $tour, ShowTour $action): TourResource
    {
        return TourResource::make($action->handle($tour));
    }

    /**
     * Update a tour.
     *
     * Partially update an active or inactive tour by ULID.
     *
     * @throws Throwable
     */
    #[Response(404, description: 'Tour not found.', type: 'array{message: string}')]
    public function update(UpdateTourRequest $request, string $tour, UpdateTour $action): TourResource
    {
        return TourResource::make($action->handle($tour, $request));
    }

    /**
     * Delete a tour.
     *
     * Soft-delete a tour by ULID while preserving all of its start dates.
     */
    #[Response(404, description: 'Tour not found.', type: 'array{message: string}')]
    public function destroy(string $tour, DeleteTour $action): HttpResponse
    {
        $action->handle($tour);

        return response()->noContent();
    }
}
