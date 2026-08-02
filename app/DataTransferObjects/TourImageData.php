<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

final readonly class TourImageData
{
    public function __construct(
        public int $id,
        public int $position,
        public bool $isCover,
        public string $name,
        public ?string $mimeType,
        public int $sizeBytes,
        public string $originalUrl,
        public string $cardUrl,
        public string $thumbnailUrl,
    ) {}

    public static function fromMedia(Media $media, ?int $position = null): self
    {
        $position ??= (int) $media->order_column;

        return new self(
            id: (int) $media->getKey(),
            position: $position,
            isCover: $position === 1,
            name: $media->name,
            mimeType: $media->mime_type,
            sizeBytes: (int) $media->size,
            originalUrl: $media->getUrl(),
            cardUrl: $media->getUrl('card'),
            thumbnailUrl: $media->getUrl('thumbnail'),
        );
    }

    /**
     * @return array{
     *     id: int,
     *     position: int,
     *     is_cover: bool,
     *     name: string,
     *     mime_type: string|null,
     *     size_bytes: int,
     *     original_url: string,
     *     card_url: string,
     *     thumbnail_url: string
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'is_cover' => $this->isCover,
            'name' => $this->name,
            'mime_type' => $this->mimeType,
            'size_bytes' => $this->sizeBytes,
            'original_url' => $this->originalUrl,
            'card_url' => $this->cardUrl,
            'thumbnail_url' => $this->thumbnailUrl,
        ];
    }
}
