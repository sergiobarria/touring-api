<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\TourImages\AddTourImages;
use App\Actions\TourImages\DeleteTourImage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTourImagesRequest;
use App\Http\Resources\TourImageResource;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

#[Group('Tour Images')]
class TourImageController extends Controller
{
    /**
     * Upload tour images.
     *
     * Add one or more JPEG, PNG, or WebP files to a tour's ordered gallery.
     *
     * @throws Throwable
     */
    #[Response(404, description: 'Tour not found.', type: 'array{message: string}')]
    #[Response(422, description: 'The upload is invalid.', type: 'array{message: string, errors: array}')]
    public function store(
        StoreTourImagesRequest $request,
        string $tour,
        AddTourImages $action,
    ): JsonResponse {
        /** @var list<UploadedFile> $images */
        $images = $request->validated('images');

        return TourImageResource::collection($action->handle($tour, $images))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Delete a tour image.
     *
     * Remove an image and its generated variants from the tour gallery.
     *
     * @throws Throwable
     */
    #[Response(404, description: 'Tour or image not found.', type: 'array{message: string}')]
    public function destroy(string $tour, string $image, DeleteTourImage $action): HttpResponse
    {
        $action->handle($tour, $image);

        return response()->noContent();
    }
}
