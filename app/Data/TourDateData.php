<?php

namespace App\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class TourDateData extends Data
{
    public function __construct(
        public string          $id,

        #[WithCast(DateTimeInterfaceCast::class)]
        public CarbonImmutable $start_datetime_utc,

        public bool            $is_active,

        public int             $available_spots
    )
    {
        // ...
    }
}
