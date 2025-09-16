<?php

namespace App\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class UpdateTourDateData extends Data
{
    public function __construct(
        #[WithCast(DateTimeInterfaceCast::class)]
        public Optional|CarbonImmutable $start_datetime_utc,

        #[IntegerType]
        public Optional|int             $available_spots,

        public Optional|bool            $is_active
    )
    {
        // ...
    }
}
