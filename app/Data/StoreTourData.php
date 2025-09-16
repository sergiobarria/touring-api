<?php

namespace App\Data;

use App\Models\Tour;
use Illuminate\Validation\Rule;
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

        public array   $images = [],
    )
    {
        // ...
    }

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'max_group_size' => ['required', 'integer', 'min:1'],
            'difficulty' => ['required', 'string', 'min:1', Rule::in(Tour::DIFFICULTY_ENUM)],
            'price' => ['required', 'numeric', 'min:0'],
            'price_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'summary' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['image', 'mimes:jpeg,png,jpg,gif,svg,webp', 'max:2048'],
        ];
    }
}
