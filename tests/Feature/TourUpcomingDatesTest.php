<?php

use App\Models\Tour;
use App\Models\TourStartDate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-08-02T12:00:00+00:00'));
});

it('returns upcoming dates on list and detail responses in chronological UTC order', function () {
    $tour = Tour::factory()->create([
        'is_active' => true,
    ]);

    TourStartDate::factory()->for($tour)->create([
        'start_datetime_utc' => '2026-08-20T15:30:00+00:00',
        'available_spots' => 8,
        'is_active' => true,
    ]);

    TourStartDate::factory()->for($tour)->create([
        'start_datetime_utc' => '2026-08-10T09:00:00+00:00',
        'available_spots' => 0,
        'is_active' => true,
    ]);

    $expectedDates = [
        '2026-08-10T09:00:00+00:00',
        '2026-08-20T15:30:00+00:00',
    ];

    $this->getJson('/api/v1/tours')
        ->assertOk()
        ->assertJsonPath('data.0.attributes.upcoming_dates', $expectedDates);

    $this->getJson("/api/v1/tours/{$tour->id}")
        ->assertOk()
        ->assertJsonPath('data.attributes.upcoming_dates', $expectedDates);
});

it('excludes non-upcoming start dates', function () {
    $tour = Tour::factory()->create([
        'is_active' => true,
    ]);

    foreach ([
        ['start_datetime_utc' => '2026-08-01T12:00:00+00:00', 'is_active' => true],
        ['start_datetime_utc' => '2026-08-02T12:00:00+00:00', 'is_active' => true],
        ['start_datetime_utc' => '2026-08-10T12:00:00+00:00', 'is_active' => false],
    ] as $attributes) {
        TourStartDate::factory()->for($tour)->create($attributes);
    }

    $deletedStartDate = TourStartDate::factory()->for($tour)->create([
        'start_datetime_utc' => '2026-08-15T12:00:00+00:00',
        'is_active' => true,
    ]);
    $deletedStartDate->delete();

    $this->getJson("/api/v1/tours/{$tour->id}")
        ->assertOk()
        ->assertJsonPath('data.attributes.upcoming_dates', []);
});

it('supports upcoming dates as a sparse tour field', function () {
    $tour = Tour::factory()->create([
        'is_active' => true,
    ]);

    TourStartDate::factory()->for($tour)->create([
        'start_datetime_utc' => '2026-08-10T09:00:00+00:00',
        'is_active' => true,
    ]);

    $this->getJson('/api/v1/tours?fields[tours]=upcoming_dates')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [[
                'id',
                'type',
                'attributes' => ['upcoming_dates'],
            ]],
        ])
        ->assertJsonPath('data.0.attributes.upcoming_dates.0', '2026-08-10T09:00:00+00:00')
        ->assertJsonMissingPath('data.0.attributes.name');
});
