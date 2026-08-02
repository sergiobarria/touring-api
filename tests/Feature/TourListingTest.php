<?php

use App\Models\Tour;
use App\Models\TourStartDate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns a paginated list of active tours', function () {
    Tour::factory()->count(16)->create([
        'is_active' => true,
    ]);

    Tour::factory()->create([
        'is_active' => false,
    ]);

    $this->getJson('/api/v1/tours')
        ->assertOk()
        ->assertJsonCount(15, 'data')
        ->assertJsonPath('meta.total', 16)
        ->assertJsonPath('meta.current_page', 1);
});

it('returns the tour duration in weeks', function () {
    Tour::factory()->create([
        'duration_days' => 10,
        'is_active' => true,
    ]);

    $this->getJson('/api/v1/tours?fields[tours]=duration_days,duration_weeks')
        ->assertOk()
        ->assertJsonPath('data.0.attributes.duration_days', 10)
        ->assertJsonPath('data.0.attributes.duration_weeks', 1.4);
});

it('supports custom page sizes and page numbers', function () {
    Tour::factory()->count(12)->create([
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/v1/tours?per_page=5&page=2')
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.per_page', 5)
        ->assertJsonPath('meta.total', 12);

    expect($response->json('links.next'))->toContain('per_page=5');
});

it('uses created date and name as the default sort', function () {
    $newer = Tour::factory()->create([
        'name' => 'Newer Tour',
        'slug' => 'newer-tour',
        'is_active' => true,
        'created_at' => now(),
    ]);

    $older = Tour::factory()->create([
        'name' => 'Older Tour',
        'slug' => 'older-tour',
        'is_active' => true,
        'created_at' => now()->subDay(),
    ]);

    $this->getJson('/api/v1/tours')
        ->assertOk()
        ->assertJsonPath('data.0.id', $older->id)
        ->assertJsonPath('data.1.id', $newer->id);
});

it('sorts tours by allowed fields', function () {
    $expensive = Tour::factory()->create([
        'name' => 'Alpine Expedition',
        'slug' => 'alpine-expedition',
        'price' => 900,
        'is_active' => true,
    ]);

    $affordable = Tour::factory()->create([
        'name' => 'Forest Walk',
        'slug' => 'forest-walk',
        'price' => 300,
        'is_active' => true,
    ]);

    $this->getJson('/api/v1/tours?sort=price')
        ->assertOk()
        ->assertJsonPath('data.0.id', $affordable->id)
        ->assertJsonPath('data.1.id', $expensive->id);

    $this->getJson('/api/v1/tours?sort=-price')
        ->assertOk()
        ->assertJsonPath('data.0.id', $expensive->id)
        ->assertJsonPath('data.1.id', $affordable->id);
});

it('supports multi-column sorting', function () {
    $second = Tour::factory()->create([
        'name' => 'Zulu Trek',
        'slug' => 'zulu-trek',
        'price' => 500,
        'is_active' => true,
    ]);

    $first = Tour::factory()->create([
        'name' => 'Alpine Trek',
        'slug' => 'alpine-trek',
        'price' => 500,
        'is_active' => true,
    ]);

    $this->getJson('/api/v1/tours?sort=price,name')
        ->assertOk()
        ->assertJsonPath('data.0.id', $first->id)
        ->assertJsonPath('data.1.id', $second->id);
});

it('filters tours by text and exact attributes', function (string $query) {
    $matching = Tour::factory()->create([
        'name' => 'Canadian Forest Hiker',
        'slug' => 'canadian-forest-hiker',
        'difficulty' => 'moderate',
        'duration_days' => 5,
        'max_group_size' => 12,
        'is_active' => true,
    ]);

    Tour::factory()->create([
        'name' => 'Desert Explorer',
        'slug' => 'desert-explorer',
        'difficulty' => 'easy',
        'duration_days' => 3,
        'max_group_size' => 20,
        'is_active' => true,
    ]);

    $this->getJson("/api/v1/tours?{$query}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matching->id);
})->with([
    'partial name' => 'filter[name]=Forest',
    'partial slug' => 'filter[slug]=forest-hiker',
    'exact difficulty' => 'filter[difficulty]=moderate',
    'exact duration' => 'filter[duration_days]=5',
    'exact group size' => 'filter[max_group_size]=12',
]);

it('filters tours by inclusive price ranges', function (string $query, array $expectedNames) {
    foreach ([300, 500, 900] as $price) {
        Tour::factory()->create([
            'name' => "Tour {$price}",
            'slug' => "tour-{$price}",
            'price' => $price,
            'is_active' => true,
        ]);
    }

    $names = collect($this->getJson("/api/v1/tours?{$query}&sort=price")
        ->assertOk()
        ->json('data'))
        ->pluck('attributes.name')
        ->all();

    expect($names)->toBe($expectedNames);
})->with([
    'minimum price' => ['filter[min_price]=500', ['Tour 500', 'Tour 900']],
    'maximum price' => ['filter[max_price]=500', ['Tour 300', 'Tour 500']],
    'combined range' => ['filter[min_price]=500&filter[max_price]=900', ['Tour 500', 'Tour 900']],
]);

it('never returns inactive tours that match filters', function () {
    Tour::factory()->create([
        'name' => 'Active Forest Tour',
        'slug' => 'active-forest-tour',
        'is_active' => true,
    ]);

    Tour::factory()->create([
        'name' => 'Inactive Forest Tour',
        'slug' => 'inactive-forest-tour',
        'is_active' => false,
    ]);

    $this->getJson('/api/v1/tours?filter[name]=Forest')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.attributes.name', 'Active Forest Tour');
});

it('includes requested tour start dates', function () {
    $tour = Tour::factory()->create([
        'is_active' => true,
    ]);

    $startDate = TourStartDate::factory()
        ->for($tour)
        ->create();

    $this->getJson('/api/v1/tours?include=startDates')
        ->assertOk()
        ->assertJsonPath('data.0.relationships.startDates.data.0.id', $startDate->id)
        ->assertJsonPath('included.0.id', $startDate->id)
        ->assertJsonPath('included.0.type', 'tour_start_dates');
});

it('applies sparse fields to tours', function () {
    Tour::factory()->create([
        'is_active' => true,
    ]);

    $this->getJson('/api/v1/tours?fields[tours]=name,difficulty')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [[
                'id',
                'type',
                'attributes' => ['name', 'difficulty'],
            ]],
        ])
        ->assertJsonMissingPath('data.0.attributes.summary');
});

it('applies sparse fields to included tour start dates', function () {
    $tour = Tour::factory()->create([
        'is_active' => true,
    ]);

    TourStartDate::factory()
        ->for($tour)
        ->create();

    $this->getJson('/api/v1/tours?include=startDates&fields[tour_start_dates]=available_spots')
        ->assertOk()
        ->assertJsonStructure([
            'included' => [[
                'id',
                'type',
                'attributes' => ['available_spots'],
            ]],
        ])
        ->assertJsonMissingPath('included.0.attributes.start_datetime_utc')
        ->assertJsonMissingPath('included.0.attributes.is_active');
});

it('rejects invalid pagination and filter values', function (string $query) {
    $this->getJson("/api/v1/tours?{$query}")
        ->assertUnprocessable();
})->with([
    'invalid page' => 'page=0',
    'invalid page size' => 'per_page=101',
    'invalid difficulty' => 'filter[difficulty]=extreme',
    'invalid duration' => 'filter[duration_days]=0',
    'invalid minimum price' => 'filter[min_price]=free',
    'reversed price range' => 'filter[min_price]=900&filter[max_price]=300',
]);

it('rejects unsupported tour query capabilities', function (string $query) {
    $this->getJson("/api/v1/tours?{$query}")
        ->assertBadRequest();
})->with([
    'include' => 'include=reviews',
    'filter' => 'filter[unknown]=value',
    'sort' => 'sort=rating_avg',
]);
