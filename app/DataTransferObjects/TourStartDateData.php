<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Models\TourStartDate;
use Carbon\CarbonImmutable;

final readonly class TourStartDateData
{
    /** @var list<string> */
    public const array WRITABLE_FIELDS = [
        'start_datetime_utc',
        'available_spots',
        'is_active',
    ];

    public function __construct(
        public CarbonImmutable $startDatetimeUtc,
        public int $availableSpots,
        public bool $isActive,
    ) {}

    /** @param array<string, mixed> $attributes */
    public static function forCreate(array $attributes): self
    {
        return new self(
            startDatetimeUtc: CarbonImmutable::parse($attributes['start_datetime_utc'])->utc(),
            availableSpots: (int) ($attributes['available_spots'] ?? 0),
            isActive: (bool) ($attributes['is_active'] ?? true),
        );
    }

    /** @param array<string, mixed> $attributes */
    public static function forUpdate(TourStartDate $startDate, array $attributes): self
    {
        return new self(
            startDatetimeUtc: array_key_exists('start_datetime_utc', $attributes)
                ? CarbonImmutable::parse($attributes['start_datetime_utc'])->utc()
                : $startDate->start_datetime_utc,
            availableSpots: (int) ($attributes['available_spots'] ?? $startDate->available_spots),
            isActive: (bool) ($attributes['is_active'] ?? $startDate->is_active),
        );
    }

    /** @return array{start_datetime_utc: CarbonImmutable, available_spots: int, is_active: bool} */
    public function toArray(): array
    {
        return [
            'start_datetime_utc' => $this->startDatetimeUtc,
            'available_spots' => $this->availableSpots,
            'is_active' => $this->isActive,
        ];
    }
}
