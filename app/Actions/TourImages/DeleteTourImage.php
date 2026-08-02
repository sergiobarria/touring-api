<?php

namespace App\Actions\TourImages;

use App\Models\Tour;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

final readonly class DeleteTourImage
{
    /** @throws Throwable */
    public function handle(string $tourId, string $imageId): void
    {
        DB::transaction(function () use ($tourId, $imageId): void {
            $tour = Tour::query()->lockForUpdate()->findOrFail($tourId);
            $media = $tour->media()
                ->where('collection_name', Tour::IMAGE_COLLECTION)
                ->whereKey($imageId)
                ->firstOrFail();

            $media->delete();

            $remainingIds = $tour->media()
                ->where('collection_name', Tour::IMAGE_COLLECTION)
                ->orderBy('order_column')
                ->pluck('id')
                ->all();

            Media::setNewOrder($remainingIds);
        });
    }
}
