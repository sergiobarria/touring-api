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

        public array           $add_images = [],
        public array           $remove_image_ids = []
    )
    {
        // ...
    }

    public static function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'min:3', 'max:255'],
            'duration_days' => ['nullable', 'integer', 'min:1'],
            'max_group_size' => ['nullable', 'integer', 'min:1'],
            'difficulty' => ['nullable', 'string', 'min:1', Rule::in(Tour::DIFFICULTY_ENUM)],
            'price' => ['nullable', 'numeric', 'min:0'],
            'price_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'summary' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
            'add_images' => ['nullable', 'array', 'max:5'],
            'add_images.*' => ['image', 'mimes:jpeg,png,jpg,gif,svg,webp', 'max:2048'],
            'remove_image_ids' => ['nullable', 'array'],
            'remove_image_ids.*' => ['integer', 'exists:media,id'],
        ];
    }
}
