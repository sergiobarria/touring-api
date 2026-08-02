<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\TourAnalytics\GetMonthlyTourPlan;
use App\Actions\TourAnalytics\GetTourStatistics;
use App\Actions\TourAnalytics\ListTopTours;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MonthlyTourPlanRequest;
use App\Http\Resources\TopTourResource;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('Tour Analytics')]
class TourAnalyticsController extends Controller
{
    /**
     * List the top tours.
     *
     * Return up to five active tours ordered by rating and price.
     */
    public function topTours(ListTopTours $action): AnonymousResourceCollection
    {
        return TopTourResource::collection($action->handle());
    }

    /**
     * Get tour statistics.
     *
     * Summarize highly rated active tours by difficulty.
     */
    public function stats(GetTourStatistics $action): JsonResponse
    {
        return response()->json(['data' => ['stats' => $action->handle()]]);
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
    public function monthlyPlan(
        MonthlyTourPlanRequest $request,
        GetMonthlyTourPlan $action,
    ): JsonResponse {
        return response()->json([
            'data' => ['plan' => $action->handle((int) $request->validated('year'))],
        ]);
    }
}
