<?php

namespace Database\Seeders;

use App\Models\Tour;
use App\Models\TourDate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class TourSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $imageDir = base_path('public/assets/tours');
        $tourImages = collect(File::files($imageDir))
            ->map(fn($file) => $file->getPathname())
            ->values();

        if ($tourImages->isEmpty()) {
            $this->command->warn("No images found in " . $imageDir);
            return;
        }

        Tour::factory(20)->create()->each(function (Tour $tour) use ($tourImages) {
            // Upload images
            $numOfImages = rand(1, 2);
            $images = $tourImages->random($numOfImages);
            foreach ($images as $image) {
                $originalExtension = File::extension($image);
                $fileName = Str::random(10) . '.' . $originalExtension;

                $tour->addMedia($image)
                    ->preservingOriginal()
                    ->usingFileName($fileName)
                    ->toMediaCollection('tours');

                $this->command->info("Tour {$tour->name} created with image {$image}");
            }

            $tour->dates()->saveMany(
                TourDate::factory()->count(rand(3, 5))->make()
            );
        });
    }
}
