<?php

use App\Models\Tour;
use App\Models\TourStartDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('returns an active tour by ulid', function () {
    $tour = Tour::factory()->create([
        'duration_days' => 10,
        'description' => 'A complete tour description.',
        'is_active' => true,
    ]);

    $this->getJson("/api/v1/tours/{$tour->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $tour->id)
        ->assertJsonPath('data.type', 'tours')
        ->assertJsonPath('data.attributes.description', 'A complete tour description.')
        ->assertJsonPath('data.attributes.duration_weeks', 1.4)
        ->assertJsonMissingPath('data.attributes.is_active')
        ->assertJsonMissingPath('data.attributes.created_at')
        ->assertJsonMissingPath('data.attributes.updated_at');
});

it('includes requested start dates', function () {
    $tour = Tour::factory()->create([
        'is_active' => true,
    ]);

    $startDate = TourStartDate::factory()
        ->for($tour)
        ->create();

    $this->getJson("/api/v1/tours/{$tour->id}?include=startDates")
        ->assertOk()
        ->assertJsonPath('data.relationships.startDates.data.0.id', $startDate->id)
        ->assertJsonPath('included.0.id', $startDate->id)
        ->assertJsonPath('included.0.type', 'tour_start_dates')
        ->assertJsonPath('included.0.attributes.available_spots', $startDate->available_spots);
});

it('applies sparse fields to a tour and its included start dates', function () {
    $tour = Tour::factory()->create([
        'description' => 'Sparse detail.',
        'is_active' => true,
    ]);

    TourStartDate::factory()
        ->for($tour)
        ->create();

    $this->getJson(
        "/api/v1/tours/{$tour->id}?include=startDates&fields[tours]=name,description&fields[tour_start_dates]=available_spots",
    )
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'type',
                'attributes' => ['name', 'description'],
            ],
            'included' => [[
                'id',
                'type',
                'attributes' => ['available_spots'],
            ]],
        ])
        ->assertJsonMissingPath('data.attributes.summary')
        ->assertJsonMissingPath('included.0.attributes.start_datetime_utc');
});

it('does not expose inactive tours', function () {
    $tour = Tour::factory()->create([
        'is_active' => false,
    ]);

    $this->getJson("/api/v1/tours/{$tour->id}")
        ->assertNotFound();
});

it('returns not found for unknown or malformed identifiers', function (string $identifier) {
    $this->getJson("/api/v1/tours/{$identifier}")
        ->assertNotFound();
})->with([
    'unknown ulid' => fn () => (string) Str::ulid(),
    'malformed identifier' => 'not-a-ulid',
]);

it('rejects unsupported includes', function () {
    $tour = Tour::factory()->create([
        'is_active' => true,
    ]);

    $this->getJson("/api/v1/tours/{$tour->id}?include=reviews")
        ->assertBadRequest();
});
