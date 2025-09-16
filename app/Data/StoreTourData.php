<?php

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Symfony\Contracts\Service\Attribute\Required;

class StoreTourData extends Data
{
    public function __construct(
        #[Required, StringType, Min(3), Max(255)]
        public string  $name,

        #[Required, IntegerType, Min(1)]
        public int     $duration_days,

        #[Required, IntegerType, Min(1)]
        public int     $max_group_size,

        #[Required, StringType, Min(1), In(['easy', 'moderate', 'difficult'])]
        public string  $difficulty,

        #[Required, Numeric, Min(0)]
        public float   $price,

        #[Numeric, Min(0), Max(100)]
        public float   $price_discount_percent,

        #[Required, StringType, Max(255)]
        public string  $summary,

        public bool    $is_active = true,

        public ?string $description = null,
    )
    {
        // ...
    }
}
