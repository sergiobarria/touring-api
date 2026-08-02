<?php

namespace App\Actions\Tours;

use App\Models\Tour;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class ShowTour
{
    public function handle(string $tourId): Tour
    {
        return QueryBuilder::for(Tour::class)
            ->with(['media', 'upcomingStartDates', 'leadGuide:id,name', 'guides:id,name'])
            ->allowedIncludes(AllowedInclude::relationship('startDates'))
            ->where('is_active', true)
            ->findOrFail($tourId);
    }
}
