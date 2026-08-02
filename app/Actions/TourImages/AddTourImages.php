<?php

namespace App\Actions\TourImages;

use App\Models\Tour;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

final readonly class AddTourImages
{
    /**
     * @param  list<UploadedFile>  $images
     * @return Collection<int, Media>
     *
     * @throws Throwable
     */
    public function handle(string $tourId, array $images): Collection
    {
        /** @var Collection<int, Media> $createdMedia */
        $createdMedia = collect();
        $existingMediaIds = [];
        $tour = null;

        DB::beginTransaction();

        try {
            $tour = Tour::query()->lockForUpdate()->findOrFail($tourId);
            $existingMediaIds = $tour->media()
                ->where('collection_name', Tour::IMAGE_COLLECTION)
                ->pluck('id')
                ->all();

            if (count($existingMediaIds) + count($images) > Tour::MAX_IMAGES) {
                throw ValidationException::withMessages([
                    'images' => 'A tour may not have more than '.Tour::MAX_IMAGES.' images.',
                ]);
            }

            foreach ($images as $image) {
                $createdMedia->push(
                    $tour
                        ->addMedia($image)
                        ->usingName($this->displayName($image))
                        ->usingFileName(Str::ulid().'.'.$this->extensionFor($image))
                        ->toMediaCollection(Tour::IMAGE_COLLECTION),
                );
            }

            DB::commit();
        } catch (Throwable $exception) {
            try {
                if ($tour !== null) {
                    $tour->media()
                        ->where('collection_name', Tour::IMAGE_COLLECTION)
                        ->whereNotIn('id', $existingMediaIds)
                        ->get()
                        ->each(fn (Media $media) => $media->delete());
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

        return $createdMedia;
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
