<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTourRequest;
use App\Http\Requests\Api\V1\TourListRequest;
use App\Http\Requests\Api\V1\UpdateTourRequest;
use App\Http\Resources\TourListResource;
use App\Http\Resources\TourResource;
use App\Models\Tour;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Header;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\QueryBuilder;

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
    public function index(TourListRequest $request): AnonymousResourceCollection
    {
        $tours = QueryBuilder::for(Tour::class)
            ->with(['media', 'upcomingStartDates'])
            ->allowedIncludes(AllowedInclude::relationship('startDates'))
            ->allowedSorts(...Tour::ALLOWED_SORTS)
            ->allowedFilters(
                'name',
                'slug',
                AllowedFilter::exact('difficulty'),
                AllowedFilter::exact('duration_days'),
                AllowedFilter::exact('max_group_size'),
                AllowedFilter::scope('min_price'),
                AllowedFilter::scope('max_price'),
            )
            ->where('is_active', true)
            ->defaultSorts('created_at', 'name')
            ->paginate(
                perPage: $request->integer('per_page', 15),
                page: $request->integer('page', 1),
            )
            ->withQueryString();

        return TourListResource::collection($tours);
    }

    /**
     * Create a tour.
     *
     * Create a tour from validated catalog data. Slugs and ratings are server-managed.
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
    public function store(StoreTourRequest $request): JsonResponse
    {
        $tour = Tour::create($request->toDto()->toArray());
        $tour->refresh()->load(['media', 'upcomingStartDates']);

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
    public function show(string $tour): TourResource
    {
        $tour = QueryBuilder::for(Tour::class)
            ->with(['media', 'upcomingStartDates'])
            ->allowedIncludes(AllowedInclude::relationship('startDates'))
            ->where('is_active', true)
            ->findOrFail($tour);

        return TourResource::make($tour);
    }

    /**
     * Update a tour.
     *
     * Partially update an active or inactive tour by ULID.
     */
    #[Response(404, description: 'Tour not found.', type: 'array{message: string}')]
    public function update(UpdateTourRequest $request, string $tour): TourResource
    {
        $tour = DB::transaction(function () use ($request, $tour): Tour {
            $lockedTour = Tour::query()->lockForUpdate()->findOrFail($tour);
            $attributes = $request->toDto($lockedTour)->toArray();

            if (array_key_exists('max_group_size', $request->validated())
                && $lockedTour->startDates()
                    ->where('available_spots', '>', $attributes['max_group_size'])
                    ->exists()) {
                throw ValidationException::withMessages([
                    'max_group_size' => 'The maximum group size must not be less than available spots on an existing start date.',
                ]);
            }

            $lockedTour->update($attributes);

            return $lockedTour->refresh();
        });

        $tour->load(['media', 'upcomingStartDates']);

        return TourResource::make($tour);
    }

    /**
     * Delete a tour.
     *
     * Soft-delete a tour by ULID while preserving all of its start dates.
     */
    #[Response(404, description: 'Tour not found.', type: 'array{message: string}')]
    public function destroy(string $tour): HttpResponse
    {
        $tour = Tour::findOrFail($tour);

        $tour->delete();

        return response()->noContent();
    }
}
