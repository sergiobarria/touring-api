<?php

namespace App\Actions\TourAnalytics;

use App\Models\Tour;

final readonly class GetTourStatistics
{
    /**
     * @return list<array{difficulty: mixed, num_tours: int, num_ratings: int, avg_rating: float, avg_price: float, min_price: float, max_price: float}>
     */
    public function handle(): array
    {
        return Tour::query()
            ->select('difficulty')
            ->selectRaw('COUNT(*) as num_tours')
            ->selectRaw('SUM(rating_count) as num_ratings')
            ->selectRaw('AVG(rating_avg) as avg_rating')
            ->selectRaw('AVG(price) as avg_price')
            ->selectRaw('MIN(price) as min_price')
            ->selectRaw('MAX(price) as max_price')
            ->where('is_active', true)
            ->where('rating_avg', '>=', 4.5)
            ->groupBy('difficulty')
            ->orderBy('avg_price')
            ->orderBy('difficulty')
            ->get()
            ->map(fn (Tour $stat): array => [
                'difficulty' => $stat->getRawOriginal('difficulty'),
                'num_tours' => (int) $stat->getAttribute('num_tours'),
                'num_ratings' => (int) $stat->getAttribute('num_ratings'),
                'avg_rating' => round((float) $stat->getAttribute('avg_rating'), 2),
                'avg_price' => round((float) $stat->getAttribute('avg_price'), 2),
                'min_price' => round((float) $stat->getAttribute('min_price'), 2),
                'max_price' => round((float) $stat->getAttribute('max_price'), 2),
            ])
            ->all();
    }
}
