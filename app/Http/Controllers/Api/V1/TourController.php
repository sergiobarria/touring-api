<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\StoreTourData;
use App\Data\TourData;
use App\Data\TourListData;
use App\Data\UpdateTourData;
use App\Http\Controllers\Controller;
use App\Http\Requests\TourListRequest;
use App\Models\Tour;
use Dedoc\Scramble\Attributes\Group;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

#[Group('Tours')]
class TourController extends Controller
{
    /** List tours. */
    public function index(TourListRequest $request)
    {
        $tours = QueryBuilder::for(Tour::class)
            ->allowedFields(Tour::ALLOWED_SELECT_FIELDS)
            ->allowedIncludes(Tour::ALLOWED_INCLUDES)
            ->allowedSorts(Tour::ALLOWED_SORTS)
            ->allowedFilters([
                'name', 'slug', 'difficulty',
                AllowedFilter::exact('duration_days'),
                AllowedFilter::exact('max_group_size'),
                AllowedFilter::scope('min_price'),
                AllowedFilter::scope('max_price'),
            ])
            ->where('is_active', true)
            ->defaultSort(['created_at', 'name'])
            ->paginate();

        return TourListData::collect($tours);
    }

    /** Create a tour */
    public function store(StoreTourData $data)
    {
        $tour = Tour::create($data->toArray());

        return TourData::from($tour)->toResponse(request())->setStatusCode(201);
    }

    /** Get Tour by ID. */
    public function show(string $id)
    {
        $tour = Tour::with('dates')->findOrFail($id);

        return TourData::from($tour);
    }

    /** Update tour */
    public function update(UpdateTourData $data, Tour $tour)
    {
        $tour->update($data->toArray());

        return TourData::from($tour);
    }

    /** Delete tour */
    public function destroy(Tour $tour)
    {
        $tour->delete();

        return response()->noContent();
    }
}
