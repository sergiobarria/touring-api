<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTourImagesRequest;
use App\Http\Resources\TourImageResource;
use App\Models\Tour;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
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
    public function store(StoreTourImagesRequest $request, string $tour): JsonResponse
    {
        /** @var Collection<int, Media> $createdMedia */
        $createdMedia = collect();
        $existingMediaIds = [];
        $lockedTour = null;

        DB::beginTransaction();

        try {
            $lockedTour = Tour::query()->lockForUpdate()->findOrFail($tour);
            $existingMediaIds = $lockedTour->media()
                ->where('collection_name', Tour::IMAGE_COLLECTION)
                ->pluck('id')
                ->all();

            /** @var list<UploadedFile> $images */
            $images = $request->validated('images');

            if (count($existingMediaIds) + count($images) > Tour::MAX_IMAGES) {
                throw ValidationException::withMessages([
                    'images' => 'A tour may not have more than '.Tour::MAX_IMAGES.' images.',
                ]);
            }

            foreach ($images as $image) {
                $createdMedia->push(
                    $lockedTour
                        ->addMedia($image)
                        ->usingName($this->displayName($image))
                        ->usingFileName(Str::ulid().'.'.$this->extensionFor($image))
                        ->toMediaCollection(Tour::IMAGE_COLLECTION),
                );
            }

            DB::commit();
        } catch (Throwable $exception) {
            try {
                if ($lockedTour !== null) {
                    $lockedTour->media()
                        ->where('collection_name', Tour::IMAGE_COLLECTION)
                        ->whereNotIn('id', $existingMediaIds)
                        ->get()
                        ->each(function (Media $media): void {
                            $media->delete();
                        });
                }
            } catch (Throwable $cleanupException) {
                report($cleanupException);
            } finally {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
            }

            throw $exception;
        }

        return TourImageResource::collection($createdMedia)
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
    public function destroy(string $tour, string $image): HttpResponse
    {
        DB::transaction(function () use ($tour, $image): void {
            $lockedTour = Tour::query()->lockForUpdate()->findOrFail($tour);
            $media = $lockedTour->media()
                ->where('collection_name', Tour::IMAGE_COLLECTION)
                ->whereKey($image)
                ->firstOrFail();

            $media->delete();

            $remainingIds = $lockedTour->media()
                ->where('collection_name', Tour::IMAGE_COLLECTION)
                ->orderBy('order_column')
                ->pluck('id')
                ->all();

            Media::setNewOrder($remainingIds);
        });

        return response()->noContent();
    }

    private function displayName(UploadedFile $image): string
    {
        $name = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME) ?: 'tour-image';

        return Str::limit($name, 255, '');
    }

    private function extensionFor(UploadedFile $image): string
    {
        return match ($image->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw ValidationException::withMessages([
                'images' => 'Every image must be a JPEG, PNG, or WebP file.',
            ]),
        };
    }
}
