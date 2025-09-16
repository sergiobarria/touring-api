<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\TourListData;
use App\Http\Controllers\Controller;
use App\Models\Tour;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;

#[Group('Tours')]
class TourAnalyticsController extends Controller
{
    /** Get top 5 tours */
    public function getTopTours()
    {
        $tours = QueryBuilder::for(Tour::class)
            ->select('id', 'name', 'price', 'rating_avg', 'summary', 'difficulty')
            ->orderByDesc('rating_avg')
            ->orderBy('price')
            ->limit(5)
            ->get();

        return TourListData::collect($tours);
    }

    /** Get tours stats */
    public function getTourStats()
    {
        $stats = Tour::query()
            ->select(
                DB::raw("UPPER(difficulty) as id"),
                DB::raw("COUNT(*) as num_tours"),
                DB::raw("SUM(rating_count) as num_ratings"),
                DB::raw("AVG(rating_avg) as rating_avg"),
                DB::raw("AVG(price) as avg_price"),
                DB::raw("MIN(price) as min_price"),
                DB::raw("MAX(price) as max_price"),
            )
            ->where('rating_avg', '>', 4.5)
            ->groupBy('difficulty')
            ->orderBy('avg_price', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /** Get tour monthly planning */
    public function getMonthlyPlan(Request $request, int $year): JsonResponse
    {
        $request->validate([
            'year' => ['required', 'integer', 'min:1900', 'max:2100']
        ]);

        $plan = Tour::query()
            ->select(
                DB::raw("EXTRACT(MONTH FROM tour_dates.start_datetime_utc) as month"),
                DB::raw("COUNT(tour_dates.id) as num_tour_starts"),
                DB::raw("STRING_AGG(tours.name, ',') as tours_names")
            )
            ->join('tour_dates', 'tours.id', '=', 'tour_dates.tour_id')
            ->whereBetween('tour_dates.start_datetime_utc', [
                "{$year}-01-01 00:00:00",
                "{$year}-12-31 23:59:59"
            ])
            ->groupBy(DB::raw('EXTRACT(MONTH FROM tour_dates.start_datetime_utc)'))
            ->orderBy('num_tour_starts', 'asc')
            ->limit(12)
            ->get();

        $plan->transform(function ($item) {
            $item->month = (int)$item->month;
            $item->tours = $item->tours_names ? explode(',', $item->tours_names) : [];
            unset($item->tours_names);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $plan
        ]);
    }
}
