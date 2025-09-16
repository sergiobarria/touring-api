<?php

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class UpdateTourData extends Data
{
    public function __construct(
        #[StringType, Min(3), Max(255)]
        public Optional|string $name,

        #[IntegerType, Min(1)]
        public Optional|int    $duration_days,

        #[IntegerType, Min(1)]
        public Optional|int    $max_group_size,

        #[StringType, Min(1), In(['easy', 'moderate', 'difficult'])]
        public Optional|string $difficulty,

        #[Numeric, Min(0)]
        public Optional|float  $price,

        #[Numeric, Min(0), Max(100)]
        public Optional|float  $price_discount_percent,

        #[StringType, Max(255)]
        public Optional|string $summary,

        public Optional|bool   $is_active = true,

        public Optional|string $description,
    )
    {
        // ...
    }
}
