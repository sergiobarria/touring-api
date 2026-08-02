<?php

use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('r2');
});

function purgeTestMedia(Tour $tour): Media
{
    $media = $tour->media()->create([
        'collection_name' => Tour::IMAGE_COLLECTION,
        'name' => 'tour',
        'file_name' => 'tour.jpg',
        'mime_type' => 'image/jpeg',
        'disk' => 'r2',
        'conversions_disk' => 'r2',
        'size' => 4,
        'manipulations' => [],
        'custom_properties' => [],
        'generated_conversions' => [],
        'responsive_images' => [],
    ]);

    Storage::disk('r2')->put($media->getPathRelativeToRoot(), 'test');

    return $media;
}

it('defaults to a dry run without deleting bucket objects or media rows', function () {
    $tour = Tour::factory()->create();
    $media = purgeTestMedia($tour);
    Storage::disk('r2')->put('orphan.txt', 'orphan');

    $this->artisan('r2:purge-media')
        ->expectsOutputToContain('Dry run only')
        ->assertSuccessful();

    Storage::disk('r2')->assertExists('orphan.txt');
    expect($media->fresh())->not->toBeNull();
});

it('purges the complete bucket and matching media rows when forced', function () {
    $tour = Tour::factory()->create();
    $media = purgeTestMedia($tour);
    Storage::disk('r2')->put('orphan.txt', 'orphan');

    $this->artisan('r2:purge-media --execute --force')
        ->expectsOutputToContain('Purged')
        ->assertSuccessful();

    expect(Storage::disk('r2')->allFiles())->toBe([])
        ->and($media->fresh())->toBeNull();
});

it('allows an interactive purge to be cancelled', function () {
    Storage::disk('r2')->put('keep.txt', 'keep');

    $this->artisan('r2:purge-media --execute')
        ->expectsConfirmation('Delete every object in this R2 bucket and all matching media rows?', 'no')
        ->expectsOutputToContain('Purge cancelled')
        ->assertSuccessful();

    Storage::disk('r2')->assertExists('keep.txt');
});

it('refuses to run outside local and testing environments', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('r2:purge-media --execute --force')
        ->expectsOutputToContain('restricted to local and testing environments')
        ->assertFailed();
});
