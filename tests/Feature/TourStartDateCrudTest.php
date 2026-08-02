<?php

use App\Models\Tour;
use App\Models\TourStartDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use OwenIt\Auditing\Models\Audit;

uses(RefreshDatabase::class);

beforeEach(fn () => authenticateTourAdmin());

it('registers nested start-date CRUD routes without put', function () {
    foreach (['index', 'store', 'show', 'update', 'destroy'] as $action) {
        expect(Route::getRoutes()->getByName("v1.tours.start-dates.{$action}"))->not->toBeNull();
    }

    expect(Route::getRoutes()->getByName('v1.tours.start-dates.update')?->methods())->toBe(['PATCH']);

    $tour = Tour::factory()->create();
    $startDate = TourStartDate::factory()->for($tour)->create();

    $this->putJson("/api/v1/tours/{$tour->id}/start-dates/{$startDate->id}", [])
        ->assertMethodNotAllowed();
});

it('lists every non-deleted start date chronologically with pagination', function () {
    $tour = Tour::factory()->create(['is_active' => false]);

    $late = TourStartDate::factory()->for($tour)->create([
        'start_datetime_utc' => '2027-02-01T10:00:00+00:00',
        'is_active' => false,
    ]);
    $early = TourStartDate::factory()->for($tour)->create([
        'start_datetime_utc' => '2020-01-01T10:00:00+00:00',
        'is_active' => true,
    ]);
    $deleted = TourStartDate::factory()->for($tour)->create([
        'start_datetime_utc' => '2021-01-01T10:00:00+00:00',
    ]);
    $deleted->delete();

    $this->getJson("/api/v1/tours/{$tour->id}/start-dates?per_page=1&page=1")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $early->id)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.per_page', 1);

    $this->getJson("/api/v1/tours/{$tour->id}/start-dates?per_page=1&page=2")
        ->assertOk()
        ->assertJsonPath('data.0.id', $late->id);
});

it('creates a start date with defaults and normalizes its instant to UTC', function () {
    config()->set('audit.console', true);

    $tour = Tour::factory()->create(['max_group_size' => 12, 'is_active' => false]);

    $response = $this->postJson("/api/v1/tours/{$tour->id}/start-dates", [
        'start_datetime_utc' => '2026-08-10T04:30:00-05:00',
    ]);

    $response
        ->assertCreated()
        ->assertHeader('Location')
        ->assertJsonPath('data.type', 'tour_start_dates')
        ->assertJsonPath('data.attributes.available_spots', 0)
        ->assertJsonPath('data.attributes.is_active', true);

    $startDate = TourStartDate::findOrFail($response->json('data.id'));

    expect(Str::isUlid($startDate->id))->toBeTrue()
        ->and($startDate->tour_id)->toBe($tour->id)
        ->and($startDate->start_datetime_utc->toIso8601String())->toBe('2026-08-10T09:30:00+00:00')
        ->and(Audit::query()
            ->where('auditable_type', TourStartDate::class)
            ->where('auditable_id', $startDate->id)
            ->exists())->toBeTrue()
        ->and($response->headers->get('Location'))->toBe(
            route('v1.tours.start-dates.show', [$tour, $startDate]),
        );
});

it('shows updates and soft deletes a start date through its parent', function () {
    $tour = Tour::factory()->create(['max_group_size' => 20]);
    $startDate = TourStartDate::factory()->for($tour)->create([
        'start_datetime_utc' => '2026-08-10T09:30:00+00:00',
        'available_spots' => 8,
        'is_active' => true,
    ]);

    $this->getJson("/api/v1/tours/{$tour->id}/start-dates/{$startDate->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $startDate->id);

    $this->patchJson("/api/v1/tours/{$tour->id}/start-dates/{$startDate->id}", [
        'available_spots' => 5,
        'is_active' => false,
    ])
        ->assertOk()
        ->assertJsonPath('data.attributes.available_spots', 5)
        ->assertJsonPath('data.attributes.is_active', false);

    $this->deleteJson("/api/v1/tours/{$tour->id}/start-dates/{$startDate->id}")
        ->assertNoContent();

    expect(TourStartDate::find($startDate->id))->toBeNull()
        ->and(TourStartDate::withTrashed()->findOrFail($startDate->id)->trashed())->toBeTrue();

    $this->getJson("/api/v1/tours/{$tour->id}/start-dates/{$startDate->id}")->assertNotFound();
    $this->deleteJson("/api/v1/tours/{$tour->id}/start-dates/{$startDate->id}")->assertNotFound();
});

it('enforces nested ownership and non-deleted parents', function () {
    $tour = Tour::factory()->create();
    $otherTour = Tour::factory()->create();
    $startDate = TourStartDate::factory()->for($otherTour)->create();

    $this->getJson("/api/v1/tours/{$tour->id}/start-dates/{$startDate->id}")->assertNotFound();
    $this->patchJson("/api/v1/tours/{$tour->id}/start-dates/{$startDate->id}", [
        'is_active' => false,
    ])->assertNotFound();
    $this->deleteJson("/api/v1/tours/{$tour->id}/start-dates/{$startDate->id}")->assertNotFound();

    $tour->delete();

    $this->getJson("/api/v1/tours/{$tour->id}/start-dates")->assertNotFound();
});

it('rejects invalid unsupported empty and over-capacity writes', function () {
    $tour = Tour::factory()->create(['max_group_size' => 10]);
    $url = "/api/v1/tours/{$tour->id}/start-dates";

    $this->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors('start_datetime_utc');
    $this->postJson($url, [
        'start_datetime_utc' => '2026-08-10 09:30:00',
    ])->assertUnprocessable()->assertJsonValidationErrors('start_datetime_utc');
    $this->postJson($url, [
        'start_datetime_utc' => '2026-08-10T09:30:00Z',
        'available_spots' => 11,
        'tour_id' => $tour->id,
    ])->assertUnprocessable()->assertJsonValidationErrors(['available_spots', 'tour_id']);

    $startDate = TourStartDate::factory()->for($tour)->create(['available_spots' => 5]);
    $itemUrl = "{$url}/{$startDate->id}";

    $this->patchJson($itemUrl, [])->assertUnprocessable()->assertJsonValidationErrors('request');
    $this->patchJson($itemUrl, ['id' => (string) Str::ulid()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['id', 'request']);
});

it('reserves equivalent start instants including soft-deleted records', function () {
    $tour = Tour::factory()->create();
    $startDate = TourStartDate::factory()->for($tour)->create([
        'start_datetime_utc' => '2026-08-10T09:30:00+00:00',
    ]);

    $this->postJson("/api/v1/tours/{$tour->id}/start-dates", [
        'start_datetime_utc' => '2026-08-10T04:30:00-05:00',
    ])->assertUnprocessable()->assertJsonValidationErrors('start_datetime_utc');

    $startDate->delete();

    $this->postJson("/api/v1/tours/{$tour->id}/start-dates", [
        'start_datetime_utc' => '2026-08-10T09:30:00Z',
    ])->assertUnprocessable()->assertJsonValidationErrors('start_datetime_utc');
});

it('rejects reducing tour capacity below existing available spots', function () {
    $tour = Tour::factory()->create(['max_group_size' => 20]);
    TourStartDate::factory()->for($tour)->create(['available_spots' => 12]);

    $this->patchJson("/api/v1/tours/{$tour->id}", ['max_group_size' => 10])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('max_group_size');

    $this->patchJson("/api/v1/tours/{$tour->id}", ['max_group_size' => 12])
        ->assertOk()
        ->assertJsonPath('data.attributes.max_group_size', 12);
});
