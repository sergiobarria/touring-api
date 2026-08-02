<?php

namespace App\Actions\Tours;

use App\Models\Tour;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class ListTours
{
    /** @return LengthAwarePaginator<int, Tour> */
    public function handle(int $perPage, int $page): LengthAwarePaginator
    {
        return QueryBuilder::for(Tour::class)
            ->with(['media', 'upcomingStartDates', 'leadGuide:id,name', 'guides:id,name'])
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
            ->paginate(perPage: $perPage, page: $page)
            ->withQueryString();
    }
}
