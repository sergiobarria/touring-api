<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Enums\TourDifficulty;
use App\Models\Tour;

final readonly class TourData
{
    /** @var list<string> */
    public const array WRITABLE_FIELDS = [
        'name',
        'lead_guide_id',
        'guide_ids',
        'duration_days',
        'max_group_size',
        'difficulty',
        'price',
        'price_discount_percent',
        'summary',
        'description',
        'is_active',
    ];

    public function __construct(
        public string $name,
        public string $leadGuideId,
        public int $durationDays,
        public int $maxGroupSize,
        public TourDifficulty $difficulty,
        public string $price,
        public ?string $priceDiscountPercent,
        public string $summary,
        public ?string $description,
        public bool $isActive,
    ) {}

    /**
     * Build complete tour data for creation.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function forCreate(array $attributes): self
    {
        return new self(
            name: $attributes['name'],
            leadGuideId: $attributes['lead_guide_id'],
            durationDays: (int) $attributes['duration_days'],
            maxGroupSize: (int) $attributes['max_group_size'],
            difficulty: TourDifficulty::from($attributes['difficulty']),
            price: (string) $attributes['price'],
            priceDiscountPercent: isset($attributes['price_discount_percent'])
                ? (string) $attributes['price_discount_percent']
                : null,
            summary: $attributes['summary'],
            description: $attributes['description'] ?? null,
            isActive: (bool) ($attributes['is_active'] ?? true),
        );
    }

    /**
     * Build complete tour data by overlaying a partial update.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function forUpdate(Tour $tour, array $attributes): self
    {
        return new self(
            name: $attributes['name'] ?? $tour->name,
            leadGuideId: $attributes['lead_guide_id'] ?? $tour->lead_guide_id,
            durationDays: (int) ($attributes['duration_days'] ?? $tour->duration_days),
            maxGroupSize: (int) ($attributes['max_group_size'] ?? $tour->max_group_size),
            difficulty: array_key_exists('difficulty', $attributes)
                ? TourDifficulty::from($attributes['difficulty'])
                : $tour->difficulty,
            price: (string) ($attributes['price'] ?? $tour->price),
            priceDiscountPercent: array_key_exists('price_discount_percent', $attributes)
                ? ($attributes['price_discount_percent'] === null
                    ? null
                    : (string) $attributes['price_discount_percent'])
                : $tour->price_discount_percent,
            summary: $attributes['summary'] ?? $tour->summary,
            description: array_key_exists('description', $attributes)
                ? $attributes['description']
                : $tour->description,
            isActive: (bool) ($attributes['is_active'] ?? $tour->is_active),
        );
    }

    /** @return array<string, bool|int|string|null> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'lead_guide_id' => $this->leadGuideId,
            'duration_days' => $this->durationDays,
            'max_group_size' => $this->maxGroupSize,
            'difficulty' => $this->difficulty->value,
            'price' => $this->price,
            'price_discount_percent' => $this->priceDiscountPercent,
            'summary' => $this->summary,
            'description' => $this->description,
            'is_active' => $this->isActive,
        ];
    }
}
