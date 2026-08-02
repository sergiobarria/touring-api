<?php

namespace App\DataTransferObjects;

final readonly class BookingData
{
    /** @param list<array{full_name: string, email: string, phone: string}> $travelers */
    public function __construct(public string $tourStartDateId, public array $travelers) {}

    /** @param array{tour_start_date_id: string, travelers: list<array{full_name: string, email: string, phone: string}>} $attributes */
    public static function from(array $attributes): self
    {
        return new self($attributes['tour_start_date_id'], array_values($attributes['travelers']));
    }

    public function requestHash(): string
    {
        return hash('sha256', json_encode([$this->tourStartDateId, $this->travelers], JSON_THROW_ON_ERROR));
    }
}
