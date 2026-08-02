<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MonthlyTourPlanRequest;
use App\Http\Resources\TopTourResource;
use App\Models\Tour;
use App\Models\TourStartDate;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;

#[Group('Tour Analytics')]
class TourAnalyticsController extends Controller
{
    /**
     * List the top tours.
     *
     * Return up to five active tours ordered by rating and price.
     */
    public function topTours(): AnonymousResourceCollection
    {
        $tours = Tour::query()
            ->with('media')
            ->where('is_active', true)
            ->orderByRaw('CASE WHEN rating_avg IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('rating_avg')
            ->orderBy('price')
            ->orderBy('id')
            ->limit(5)
            ->get();

        return TopTourResource::collection($tours);
    }

    /**
     * Get tour statistics.
     *
     * Summarize highly rated active tours by difficulty.
     */
    public function stats(): JsonResponse
    {
        $stats = Tour::query()
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

        return response()->json([
            'data' => [
                'stats' => $stats,
            ],
        ]);
    }

    /**
     * Get a monthly tour plan.
     *
     * Group active tour departures in a UTC calendar year by month.
     */
    #[PathParameter(
        'year',
        description: 'Four-digit UTC calendar year from 1000 through 9999.',
        type: 'integer',
        infer: false,
        example: 2026,
    )]
    #[Response(
        200,
        type: 'array{data: array{plan: list<array{month: int, num_tour_starts: int, tours: list<string>}>}}',
    )]
    public function monthlyPlan(MonthlyTourPlanRequest $request): JsonResponse
    {
        $year = (int) $request->validated('year');
        $yearStart = CarbonImmutable::create($year, 1, 1, 0, 0, 0, 'UTC');
        $yearEnd = $yearStart->endOfYear();

        $startDates = TourStartDate::query()
            ->select(['id', 'tour_id', 'start_datetime_utc'])
            ->with('tour:id,name')
            ->where('is_active', true)
            ->whereBetween('start_datetime_utc', [$yearStart, $yearEnd])
            ->whereHas(
                'tour',
                fn (Builder $query): Builder => $query->where('is_active', true),
            )
            ->orderBy('start_datetime_utc')
            ->orderBy('id')
            ->get();

        $plan = $startDates
            ->groupBy(fn (TourStartDate $startDate): int => $startDate->start_datetime_utc->month)
            ->map(fn (Collection $departures, int $month): array => [
                'month' => $month,
                'num_tour_starts' => $departures->count(),
                'tours' => $departures
                    ->map(fn (TourStartDate $startDate): string => $startDate->tour->name)
                    ->all(),
            ])
            ->sort(function (array $left, array $right): int {
                return ($right['num_tour_starts'] <=> $left['num_tour_starts'])
                    ?: ($left['month'] <=> $right['month']);
            })
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'plan' => $plan,
            ],
        ]);
    }
}
