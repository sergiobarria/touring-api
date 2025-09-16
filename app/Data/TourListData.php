<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class TourListData extends Data
{
    public function __construct(
        public Optional|string $id,
        public Optional|string $name,
        public Optional|string $slug,
        public Optional|int    $duration_days,
        public Optional|int    $max_group_size,
        public Optional|string $difficulty,
        public Optional|float  $rating_avg,
        public Optional|int    $rating_count,
        public Optional|float  $price,
        public Optional|float  $price_discount_percent,
        public Optional|string $summary,

        /** @var array<TourDateData> */
        public Optional|array  $dates,

        /** @var array<string> */
        public array           $images_urls
    )
    {
        // ...
    }
}
