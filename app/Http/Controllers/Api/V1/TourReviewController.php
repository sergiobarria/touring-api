<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Reviews\CreateTourReview;
use App\Actions\Reviews\DeleteTourReview;
use App\Actions\Reviews\ListTourReviews;
use App\Actions\Reviews\ShowTourReview;
use App\Actions\Reviews\UpdateTourReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReviewListRequest;
use App\Http\Requests\Api\V1\StoreReviewRequest;
use App\Http\Requests\Api\V1\UpdateReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Tour;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Header;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;
use Throwable;

#[Group('Tour Reviews')]
class TourReviewController extends Controller
{
    public function index(ReviewListRequest $request, Tour $tour, ListTourReviews $action): AnonymousResourceCollection
    {
        return ReviewResource::collection($action->handle(
            $tour,
            $request->integer('per_page', 15),
            $request->integer('page', 1),
        ));
    }

    /** @throws Throwable */
    #[Response(201, description: 'Review created.', mediaType: 'application/vnd.api+json', type: ReviewResource::class)]
    #[Header('Location', description: 'URL of the created review.', type: 'string', format: 'uri', required: true, status: 201)]
    public function store(StoreReviewRequest $request, Tour $tour, CreateTourReview $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $review = $action->handle($tour, $user, $request->toDto());

        return ReviewResource::make($review)->response()->setStatusCode(201)
            ->header('Location', route('v1.tours.reviews.show', [$tour, $review]));
    }

    public function show(Tour $tour, string $review, ShowTourReview $action): ReviewResource
    {
        return ReviewResource::make($action->handle($tour, $review));
    }

    /** @throws Throwable */
    public function update(UpdateReviewRequest $request, Tour $tour, string $review, UpdateTourReview $action): ReviewResource
    {
        return ReviewResource::make($action->handle($tour, $review, $request->validated()));
    }

    /** @throws Throwable */
    public function destroy(Tour $tour, string $review, DeleteTourReview $action): HttpResponse
    {
        $action->handle($tour, $review);

        return response()->noContent();
    }
}
