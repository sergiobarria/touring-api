<?php

namespace App\Actions\Reviews;

use App\Models\Review;
use App\Models\Tour;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ListTourReviews
{
    /** @return LengthAwarePaginator<int, Review> */
    public function handle(Tour $tour, int $perPage, int $page): LengthAwarePaginator
    {
        return $tour->reviews()->with('user:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(perPage: $perPage, page: $page)
            ->withQueryString();
    }
}
