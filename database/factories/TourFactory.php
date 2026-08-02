<?php

namespace Database\Factories;

use App\Enums\TourDifficulty;
use App\Enums\UserRole;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * @extends Factory<Tour>
 */
class TourFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->bothify('Tour ???-###');
        $hasRatings = fake()->boolean(80);

        return [
            'name' => $name,
            'lead_guide_id' => User::factory()->withRole(UserRole::LEAD_GUIDE),
            'slug' => Str::slug($name),
            'duration_days' => fake()->numberBetween(1, 30),
            'max_group_size' => fake()->numberBetween(4, 20),
            'difficulty' => fake()->randomElement(TourDifficulty::cases()),
            'price' => fake()->randomFloat(2, 299, 2999),
            'price_discount_percent' => fake()
                ->optional(0.3)
                ->randomElement([5, 10, 15, 20, 25]),
            'rating_avg' => $hasRatings
                ? fake()->randomFloat(2, 3.5, 5)
                : null,
            'rating_count' => $hasRatings
                ? fake()->numberBetween(1, 247)
                : 0,
            'summary' => fake()->sentence(12),
            'description' => fake()->optional(0.9)->paragraph(3, true),
        ];
    }

    /**
     * Assign a known, role-appropriate guide team after creating the tour.
     *
     * @param  list<User>  $guides
     */
    public function withGuideTeam(User $leadGuide, array $guides = []): static
    {
        if (count($guides) > Tour::MAX_SUPPORTING_GUIDES) {
            throw new InvalidArgumentException('A tour may not have more than four supporting guides.');
        }

        return $this
            ->state(['lead_guide_id' => $leadGuide->getKey()])
            ->afterCreating(fn (Tour $tour) => $tour->guides()->sync(
                collect($guides)->map(fn (User $guide): string => $guide->getKey())->all(),
            ));
    }

    /**
     * Attach random tracked demo images after creating the tour.
     */
    public function withImages(int $minimum = 1, int $maximum = 2): static
    {
        if ($minimum < 1 || $maximum < $minimum || $maximum > Tour::MAX_IMAGES) {
            throw new InvalidArgumentException(
                'Image counts must satisfy 1 <= minimum <= maximum <= '.Tour::MAX_IMAGES.'.',
            );
        }

        return $this->afterCreating(function (Tour $tour) use ($minimum, $maximum): void {
            $imagePaths = collect(File::files(base_path('data/assets')))
                ->filter(fn ($file): bool => in_array(
                    strtolower($file->getExtension()),
                    ['jpg', 'jpeg', 'png', 'webp'],
                    strict: true,
                ))
                ->values();

            if ($imagePaths->isEmpty()) {
                throw new RuntimeException('No tour image fixtures were found in data/assets.');
            }

            $imageCount = fake()->numberBetween($minimum, $maximum);

            for ($index = 0; $index < $imageCount; $index++) {
                $path = $imagePaths->random()->getPathname();
                $extension = strtolower(File::extension($path));

                $tour
                    ->copyMedia($path)
                    ->usingName(File::name($path))
                    ->usingFileName(Str::ulid().'.'.$extension)
                    ->toMediaCollection(Tour::IMAGE_COLLECTION);
            }
        });
    }
}
