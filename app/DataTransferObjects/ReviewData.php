<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Models\Review;

final readonly class ReviewData
{
    /** @var list<string> */
    public const array WRITABLE_FIELDS = ['rating', 'review'];

    public function __construct(public int $rating, public string $review) {}

    /** @param array<string, mixed> $attributes */
    public static function forCreate(array $attributes): self
    {
        return new self((int) $attributes['rating'], trim((string) $attributes['review']));
    }

    /** @param array<string, mixed> $attributes */
    public static function forUpdate(Review $review, array $attributes): self
    {
        return new self(
            (int) ($attributes['rating'] ?? $review->rating),
            array_key_exists('review', $attributes) ? trim((string) $attributes['review']) : $review->review,
        );
    }

    /** @return array{rating: int, review: string} */
    public function toArray(): array
    {
        return ['rating' => $this->rating, 'review' => $this->review];
    }
}
