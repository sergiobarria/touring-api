<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TourListResource;
use App\Http\Resources\TourResource;
use App\Models\Tour;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TourController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'per_page' => 'integer|between:1,100',
            'page' => 'integer|min:1',
            'include' => 'string',
            'filter' => 'array',
            'filter.*' => 'string',
        ]);

        $tours = QueryBuilder::for(Tour::class)
            ->allowedIncludes(['schedules'])
            ->allowedSorts(['name', 'price', 'max_group_size', 'duration_days', 'created_at'])
            ->allowedFilters([
                'name', 'slug', 'difficulty',
                AllowedFilter::exact('duration_days'),
                AllowedFilter::exact('max_group_size'),
                AllowedFilter::scope('min_price'),
                AllowedFilter::scope('max_price')
            ])
            ->defaultSort('-created_at')
            ->paginate($request->get('per_page', 10));

        return TourListResource::collection($tours);
    }

    public function show(Tour $tour)
    {
        $tour->load('schedules');

        return new TourResource($tour);
    }

    public function store()
    {
        return response()->json('Save tour');
    }

    public function update()
    {
        return response()->json('Update tour');
    }

    public function destroy()
    {
        return response()->json('Delete tour');
    }
}
