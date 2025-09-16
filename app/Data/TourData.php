<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class TourData extends Data
{
    public function __construct(
        public string  $name,
        public string  $slug,
        public int     $duration_days,
        public int     $max_group_size,
        public string  $difficulty,
        public ?float  $rating_avg,
        public ?int    $rating_count,
        public float   $price,
        public ?float  $price_discount_percent,
        public string  $summary,
        public ?string $description,
        public bool    $is_active,

        // Relations
        /** @var array<TourDateData> */
        public ?array  $dates
    )
    {
        // ...
    }
}
