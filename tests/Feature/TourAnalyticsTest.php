<?php

use App\Models\Tour;
use App\Models\TourStartDate;
use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(fn () => authenticateTourAdmin());

it('registers the tour analytics routes as get endpoints', function () {
    foreach (['top-tours', 'stats', 'monthly-plan'] as $action) {
        $route = Route::getRoutes()->getByName("v1.tour-analytics.{$action}");

        expect($route)->not->toBeNull()
            ->and($route?->methods())->toBe(['GET', 'HEAD']);
    }
});

it('adds an index for analytics date range queries', function () {
    expect(Schema::hasIndex('tour_start_dates', ['start_datetime_utc']))->toBeTrue();
});

it('documents the fixed top tour attributes as required', function () {
    $this->withoutMiddleware(RestrictedDocsAccess::class);

    $this->getJson('/docs/v1.json')
        ->assertOk()
        ->assertJsonPath('components.schemas.TopTourResource.required', [
            'id',
            'type',
            'attributes',
        ])
        ->assertJsonPath('components.schemas.TopTourResource.properties.attributes.required', [
            'name',
            'price',
            'rating_avg',
            'summary',
            'difficulty',
            'images',
        ]);
});

it('returns five top active tours with a fixed fieldset and portable null ordering', function () {
    $expected = collect([
        ['id' => '01H00000000000000000000001', 'name' => 'First tie', 'rating_avg' => 5, 'price' => 300],
        ['id' => '01H00000000000000000000002', 'name' => 'Second tie', 'rating_avg' => 5, 'price' => 300],
        ['id' => '01H00000000000000000000003', 'name' => 'Pricier five', 'rating_avg' => 5, 'price' => 500],
        ['id' => '01H00000000000000000000004', 'name' => 'Rated fourth', 'rating_avg' => 4.8, 'price' => 100],
        ['id' => '01H00000000000000000000005', 'name' => 'Rated fifth', 'rating_avg' => 4.7, 'price' => 100],
        ['id' => '01H00000000000000000000006', 'name' => 'Unrated', 'rating_avg' => null, 'price' => 1],
    ])->map(fn (array $attributes): Tour => Tour::factory()->create([
        ...$attributes,
        'slug' => str($attributes['name'])->slug()->toString(),
        'is_active' => true,
    ]));

    Tour::factory()->create(['rating_avg' => 5, 'is_active' => false]);
    $deleted = Tour::factory()->create(['rating_avg' => 5, 'is_active' => true]);
    $deleted->delete();

    $response = $this->getJson('/api/v1/tour-analytics/top-tours?fields[tours]=name&sort=price')
        ->assertOk()
        ->assertJsonCount(5, 'data');

    expect(collect($response->json('data'))->pluck('id')->all())
        ->toBe($expected->take(5)->pluck('id')->all());

    $response->assertJsonStructure([
        'data' => [[
            'id',
            'type',
            'attributes' => ['name', 'price', 'rating_avg', 'summary', 'difficulty', 'images'],
        ]],
    ])->assertJsonMissingPath('data.0.attributes.slug');
});

it('groups highly rated active tours into normalized statistics', function () {
    foreach ([
        ['difficulty' => 'easy', 'rating_avg' => 4.5, 'rating_count' => 10, 'price' => 100],
        ['difficulty' => 'easy', 'rating_avg' => 5, 'rating_count' => 20, 'price' => 300],
        ['difficulty' => 'moderate', 'rating_avg' => 4.8, 'rating_count' => 7, 'price' => 100],
        ['difficulty' => 'difficult', 'rating_avg' => 4.49, 'rating_count' => 99, 'price' => 1],
    ] as $attributes) {
        Tour::factory()->create([...$attributes, 'is_active' => true]);
    }

    Tour::factory()->create(['rating_avg' => 5, 'is_active' => false]);
    $deleted = Tour::factory()->create(['rating_avg' => 5, 'is_active' => true]);
    $deleted->delete();

    $this->getJson('/api/v1/tour-analytics/stats')
        ->assertOk()
        ->assertExactJson([
            'data' => [
                'stats' => [
                    [
                        'difficulty' => 'moderate',
                        'num_tours' => 1,
                        'num_ratings' => 7,
                        'avg_rating' => 4.8,
                        'avg_price' => 100,
                        'min_price' => 100,
                        'max_price' => 100,
                    ],
                    [
                        'difficulty' => 'easy',
                        'num_tours' => 2,
                        'num_ratings' => 30,
                        'avg_rating' => 4.75,
                        'avg_price' => 200,
                        'min_price' => 100,
                        'max_price' => 300,
                    ],
                ],
            ],
        ]);
});

it('returns empty analytics arrays when no records qualify', function () {
    $this->getJson('/api/v1/tour-analytics/stats')
        ->assertOk()
        ->assertExactJson(['data' => ['stats' => []]]);

    $this->getJson('/api/v1/tour-analytics/monthly-plan/2026')
        ->assertOk()
        ->assertExactJson(['data' => ['plan' => []]]);
});

it('builds a deterministic active-only monthly plan within utc year boundaries', function () {
    $forest = Tour::factory()->create(['name' => 'Forest Hiker', 'is_active' => true]);
    $sea = Tour::factory()->create(['name' => 'Sea Explorer', 'is_active' => true]);

    foreach ([
        [$forest, '2026-01-01T00:00:00+00:00'],
        [$forest, '2026-05-01T08:00:00+00:00'],
        [$sea, '2026-05-02T08:00:00+00:00'],
        [$forest, '2026-05-03T08:00:00+00:00'],
        [$forest, '2026-12-31T23:59:59+00:00'],
    ] as [$tour, $instant]) {
        TourStartDate::factory()->for($tour)->create([
            'start_datetime_utc' => $instant,
            'is_active' => true,
        ]);
    }

    TourStartDate::factory()->for($forest)->create([
        'start_datetime_utc' => '2026-06-01T00:00:00+00:00',
        'is_active' => false,
    ]);

    $deletedDate = TourStartDate::factory()->for($forest)->create([
        'start_datetime_utc' => '2026-07-01T00:00:00+00:00',
        'is_active' => true,
    ]);
    $deletedDate->delete();

    $inactiveTour = Tour::factory()->create(['is_active' => false]);
    TourStartDate::factory()->for($inactiveTour)->create([
        'start_datetime_utc' => '2026-08-01T00:00:00+00:00',
        'is_active' => true,
    ]);

    $deletedTour = Tour::factory()->create(['is_active' => true]);
    TourStartDate::factory()->for($deletedTour)->create([
        'start_datetime_utc' => '2026-09-01T00:00:00+00:00',
        'is_active' => true,
    ]);
    $deletedTour->delete();

    $this->getJson('/api/v1/tour-analytics/monthly-plan/2026')
        ->assertOk()
        ->assertExactJson([
            'data' => [
                'plan' => [
                    [
                        'month' => 5,
                        'num_tour_starts' => 3,
                        'tours' => ['Forest Hiker', 'Sea Explorer', 'Forest Hiker'],
                    ],
                    ['month' => 1, 'num_tour_starts' => 1, 'tours' => ['Forest Hiker']],
                    ['month' => 12, 'num_tour_starts' => 1, 'tours' => ['Forest Hiker']],
                ],
            ],
        ]);
});

it('counts historical departures created through the start date api', function () {
    $tour = Tour::factory()->create([
        'name' => 'Historic Trail',
        'max_group_size' => 12,
        'is_active' => true,
    ]);

    $this->postJson("/api/v1/tours/{$tour->id}/start-dates", [
        'start_datetime_utc' => '2020-05-10T04:30:00-05:00',
        'available_spots' => 8,
        'is_active' => true,
    ])->assertCreated();

    $this->getJson('/api/v1/tour-analytics/monthly-plan/2020')
        ->assertOk()
        ->assertJsonPath('data.plan.0', [
            'month' => 5,
            'num_tour_starts' => 1,
            'tours' => ['Historic Trail'],
        ]);
});

it('includes the final instant of year 9999', function () {
    $tour = Tour::factory()->create(['name' => 'Last Tour', 'is_active' => true]);

    TourStartDate::factory()->for($tour)->create([
        'start_datetime_utc' => '9999-12-31T23:59:59+00:00',
        'is_active' => true,
    ]);

    $this->getJson('/api/v1/tour-analytics/monthly-plan/9999')
        ->assertOk()
        ->assertJsonPath('data.plan.0.month', 12)
        ->assertJsonPath('data.plan.0.num_tour_starts', 1);
});

it('rejects invalid monthly plan years', function (string $year) {
    $this->getJson("/api/v1/tour-analytics/monthly-plan/{$year}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('year');
})->with(['999', '10000', 'abcd', '20.26']);
