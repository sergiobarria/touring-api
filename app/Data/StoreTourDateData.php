<?php

namespace App\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class StoreTourDateData extends Data
{
    public function __construct(
        #[WithCast(DateTimeInterfaceCast::class)]
        public CarbonImmutable $start_datetime_utc,

        #[IntegerType]
        public ?int            $available_spots,

        public bool            $is_active = true
    )
    {
        // ...
    }
}
