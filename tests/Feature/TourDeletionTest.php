<?php

use App\Models\Tour;
use App\Models\TourStartDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(fn () => authenticateTourAdmin());

it('soft deletes active and inactive tours', function (bool $isActive) {
    $tour = Tour::factory()->create([
        'is_active' => $isActive,
    ]);

    $this->deleteJson("/api/v1/tours/{$tour->id}")
        ->assertNoContent();

    expect(Tour::find($tour->id))->toBeNull()
        ->and(Tour::withTrashed()->findOrFail($tour->id)->trashed())->toBeTrue();
})->with([
    'active tour' => true,
    'inactive tour' => false,
]);

it('preserves start dates when their tour is deleted', function () {
    $tour = Tour::factory()->create();
    $startDate = TourStartDate::factory()->for($tour)->create();

    $this->deleteJson("/api/v1/tours/{$tour->id}")
        ->assertNoContent();

    expect(TourStartDate::find($startDate->id))
        ->not->toBeNull()
        ->and(TourStartDate::withTrashed()->findOrFail($startDate->id)->trashed())
        ->toBeFalse();
});

it('does not return a deleted tour from list or detail endpoints', function () {
    $tour = Tour::factory()->create([
        'is_active' => true,
    ]);

    $this->deleteJson("/api/v1/tours/{$tour->id}")
        ->assertNoContent();

    $this->getJson('/api/v1/tours')
        ->assertOk()
        ->assertJsonMissing(['id' => $tour->id]);

    $this->getJson("/api/v1/tours/{$tour->id}")
        ->assertNotFound();
});

it('returns not found for unknown or malformed identifiers', function (string $identifier) {
    $this->deleteJson("/api/v1/tours/{$identifier}")
        ->assertNotFound();
})->with([
    'unknown ulid' => fn () => (string) Str::ulid(),
    'malformed identifier' => 'not-a-ulid',
]);

it('returns not found when deleting a tour more than once', function () {
    $tour = Tour::factory()->create();

    $this->deleteJson("/api/v1/tours/{$tour->id}")
        ->assertNoContent();

    $this->deleteJson("/api/v1/tours/{$tour->id}")
        ->assertNotFound();
});

it('soft deletes tour start dates at the model level', function () {
    $startDate = TourStartDate::factory()
        ->for(Tour::factory())
        ->create();

    $startDate->delete();

    expect(TourStartDate::find($startDate->id))->toBeNull()
        ->and(TourStartDate::withTrashed()->findOrFail($startDate->id)->trashed())->toBeTrue();
});
