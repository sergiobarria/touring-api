<?php

use App\Models\Tour;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem as FilesystemContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Filesystem as MediaFilesystem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\FileRemover\FileBaseFileRemover;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    authenticateTourAdmin();
    config(['filesystems.disks.r2.url' => 'https://media.test']);
    Storage::fake('r2', ['url' => 'https://media.test']);
});

function tourImage(string $name = 'tour.jpg'): UploadedFile
{
    return UploadedFile::fake()->image($name, 1500, 1000);
}

function tourMedia(Tour $tour, string $fileName = 'tour.jpg'): Media
{
    $media = $tour->media()->create([
        'collection_name' => Tour::IMAGE_COLLECTION,
        'name' => pathinfo($fileName, PATHINFO_FILENAME),
        'file_name' => $fileName,
        'mime_type' => 'image/jpeg',
        'disk' => 'r2',
        'conversions_disk' => 'r2',
        'size' => 4,
        'manipulations' => [],
        'custom_properties' => [],
        'generated_conversions' => [
            'card' => true,
            'thumbnail' => true,
        ],
        'responsive_images' => [],
    ]);

    Storage::disk('r2')->put($media->getPathRelativeToRoot(), 'test');
    Storage::disk('r2')->put($media->getPathRelativeToRoot('card'), 'test');
    Storage::disk('r2')->put($media->getPathRelativeToRoot('thumbnail'), 'test');

    return $media;
}

it('uploads multiple ordered images with immediate variants', function () {
    $tour = Tour::factory()->create();

    $response = $this->post("/api/v1/tours/{$tour->id}/images", [
        'images' => [
            tourImage('alpine.jpg'),
            tourImage('forest.png'),
        ],
    ], ['Accept' => 'application/json']);

    $response
        ->assertCreated()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.type', 'tour_images')
        ->assertJsonPath('data.0.attributes.position', 1)
        ->assertJsonPath('data.0.attributes.is_cover', true)
        ->assertJsonPath('data.0.attributes.name', 'alpine')
        ->assertJsonPath('data.1.attributes.position', 2)
        ->assertJsonPath('data.1.attributes.is_cover', false);

    $media = $tour->refresh()->getMedia(Tour::IMAGE_COLLECTION);

    expect($media)->toHaveCount(2)
        ->and($media->every(fn (Media $image): bool => $image->disk === 'r2'))->toBeTrue()
        ->and($media->every(fn (Media $image): bool => $image->hasGeneratedConversion('card')))->toBeTrue()
        ->and($media->every(fn (Media $image): bool => $image->hasGeneratedConversion('thumbnail')))->toBeTrue();

    foreach ($media as $image) {
        Storage::disk('r2')->assertExists($image->getPathRelativeToRoot());
        Storage::disk('r2')->assertExists($image->getPathRelativeToRoot('card'));
        Storage::disk('r2')->assertExists($image->getPathRelativeToRoot('thumbnail'));
    }
});

it('returns ordered image objects in all tour payloads', function () {
    $tour = Tour::factory()->create([
        'is_active' => true,
        'rating_avg' => 5,
    ]);
    tourMedia($tour, 'payload.jpg');

    foreach ([
        "/api/v1/tours/{$tour->id}",
        '/api/v1/tours',
        '/api/v1/tour-analytics/top-tours',
    ] as $endpoint) {
        $response = $this->getJson($endpoint)->assertOk();
        $path = $endpoint === "/api/v1/tours/{$tour->id}"
            ? 'data.attributes.images.0'
            : 'data.0.attributes.images.0';

        $response
            ->assertJsonPath("{$path}.id", $tour->getFirstMedia(Tour::IMAGE_COLLECTION)->id)
            ->assertJsonPath("{$path}.position", 1)
            ->assertJsonPath("{$path}.is_cover", true)
            ->assertJsonPath("{$path}.name", 'payload')
            ->assertJsonPath("{$path}.mime_type", 'image/jpeg')
            ->assertJsonPath("{$path}.original_url", fn (string $url): bool => str_starts_with($url, 'https://media.test/')
                && str_contains($url, '/'.$tour->getFirstMedia(Tour::IMAGE_COLLECTION)->id.'/'))
            ->assertJsonPath("{$path}.card_url", fn (string $url): bool => str_ends_with($url, '-card.webp'))
            ->assertJsonPath("{$path}.thumbnail_url", fn (string $url): bool => str_ends_with($url, '-thumbnail.webp'));
    }
});

it('supports image sparse fields on tour resources', function () {
    $tour = Tour::factory()->create(['is_active' => true]);
    tourMedia($tour);

    $this->getJson("/api/v1/tours/{$tour->id}?fields[tours]=name")
        ->assertOk()
        ->assertJsonMissingPath('data.attributes.images');

    $this->getJson("/api/v1/tours/{$tour->id}?fields[tours]=images")
        ->assertOk()
        ->assertJsonPath('data.attributes.images.0.is_cover', true)
        ->assertJsonMissingPath('data.attributes.name');
});

it('eager loads media for catalog payloads', function () {
    Tour::factory()->count(3)->create(['is_active' => true]);
    $mediaQueries = [];

    DB::listen(function ($query) use (&$mediaQueries): void {
        if (str_contains($query->sql, 'from "media"')) {
            $mediaQueries[] = $query->sql;
        }
    });

    $this->getJson('/api/v1/tours')->assertOk();

    expect($mediaQueries)->toHaveCount(1);
});

it('enforces supported image types, sizes, fields, and request count', function (array|Closure $payload, string $field) {
    $tour = Tour::factory()->create();
    $payload = value($payload);

    $this->post("/api/v1/tours/{$tour->id}/images", $payload, ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'empty images' => [['images' => []], 'images'],
    'unsupported type' => [['images' => [UploadedFile::fake()->create('tour.gif', 10, 'image/gif')]], 'images.0'],
    'oversized image' => [['images' => [UploadedFile::fake()->create('tour.jpg', 10241, 'image/jpeg')]], 'images.0'],
    'too many images' => [fn (): array => [
        'images' => collect(range(1, 11))
            ->map(fn (int $index): UploadedFile => tourImage("tour-{$index}.jpg"))
            ->all(),
    ], 'images'],
    'unsupported field' => [['images' => [tourImage()], 'unexpected' => true], 'unexpected'],
]);

it('enforces the cumulative tour image limit without partial uploads', function () {
    $tour = Tour::factory()->create();

    foreach (range(1, 9) as $index) {
        tourMedia($tour, "existing-{$index}.jpg");
    }

    $this->post("/api/v1/tours/{$tour->id}/images", [
        'images' => [tourImage('extra-1.jpg'), tourImage('extra-2.jpg')],
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('images');

    expect($tour->refresh()->getMedia(Tour::IMAGE_COLLECTION))->toHaveCount(9);
});

it('deletes only an owned tour image and normalizes the remaining positions', function () {
    $tour = Tour::factory()->create();
    $otherTour = Tour::factory()->create();
    $first = tourMedia($tour, 'first.jpg');
    $second = tourMedia($tour, 'second.jpg');
    $foreign = tourMedia($otherTour, 'foreign.jpg');
    $deletedPaths = [
        $first->getPathRelativeToRoot(),
        $first->getPathRelativeToRoot('card'),
        $first->getPathRelativeToRoot('thumbnail'),
    ];

    $this->deleteJson("/api/v1/tours/{$tour->id}/images/{$foreign->id}")->assertNotFound();
    $this->deleteJson("/api/v1/tours/{$tour->id}/images/{$first->id}")->assertNoContent();

    expect(Media::find($first->id))->toBeNull()
        ->and($second->refresh()->order_column)->toBe(1)
        ->and($foreign->refresh())->not->toBeNull();

    foreach ($deletedPaths as $path) {
        Storage::disk('r2')->assertMissing($path);
    }
});

it('preserves the media row when storage deletion fails', function () {
    $tour = Tour::factory()->create();
    $media = tourMedia($tour, 'undeletable.jpg');
    $disk = Mockery::mock(FilesystemContract::class);
    $filesystem = Mockery::mock(FilesystemFactory::class);

    $disk->shouldReceive('delete')
        ->once()
        ->with($media->getPathRelativeToRoot())
        ->andThrow(new RuntimeException('R2 deletion failed.'));
    $filesystem->shouldReceive('disk')
        ->once()
        ->with('r2')
        ->andReturn($disk);

    app()->instance(
        FileBaseFileRemover::class,
        new FileBaseFileRemover(app(MediaFilesystem::class), $filesystem),
    );

    $this->deleteJson("/api/v1/tours/{$tour->id}/images/{$media->id}")
        ->assertInternalServerError();

    expect(Media::find($media->id))->not->toBeNull();
});

it('allows inactive tours but rejects soft-deleted tours for image writes', function () {
    $inactive = Tour::factory()->create(['is_active' => false]);

    $this->post("/api/v1/tours/{$inactive->id}/images", [
        'images' => [tourImage()],
    ], ['Accept' => 'application/json'])->assertCreated();

    $inactive->delete();

    $this->post("/api/v1/tours/{$inactive->id}/images", [
        'images' => [tourImage('another.jpg')],
    ], ['Accept' => 'application/json'])->assertNotFound();

    expect($inactive->media()->count())->toBe(1);
});

it('attaches random images through the factory state without moving fixtures', function () {
    $fixture = base_path('data/assets/tour-1.jpg');

    $tour = Tour::factory()->withImages(2, 2)->create();

    expect($tour->getMedia(Tour::IMAGE_COLLECTION))->toHaveCount(2)
        ->and(file_exists($fixture))->toBeTrue();
});
