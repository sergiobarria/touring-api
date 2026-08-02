<?php

namespace App\Actions\TourAnalytics;

use App\Models\Tour;
use Illuminate\Database\Eloquent\Collection;

final readonly class ListTopTours
{
    /** @return Collection<int, Tour> */
    public function handle(): Collection
    {
        return Tour::query()
            ->with('media')
            ->where('is_active', true)
            ->orderByRaw('CASE WHEN rating_avg IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('rating_avg')
            ->orderBy('price')
            ->orderBy('id')
            ->limit(5)
            ->get();
    }
}
