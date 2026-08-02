<?php

use App\Models\Tour;
use App\Models\TourStartDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function validTourPayload(array $overrides = []): array
{
    return array_replace([
        'name' => 'The Forest Hiker',
        'duration_days' => 5,
        'max_group_size' => 25,
        'difficulty' => 'easy',
        'price' => 397,
        'summary' => 'Breathtaking hike through the Canadian Banff National Park.',
    ], $overrides);
}

it('creates a tour and returns its resource location', function () {
    $response = $this->postJson('/api/v1/tours', validTourPayload());

    $response
        ->assertCreated()
        ->assertHeader('Location')
        ->assertJsonPath('data.type', 'tours')
        ->assertJsonPath('data.attributes.name', 'The Forest Hiker')
        ->assertJsonPath('data.attributes.slug', 'the-forest-hiker')
        ->assertJsonPath('data.attributes.price', '397.00')
        ->assertJsonPath('data.attributes.rating_avg', null)
        ->assertJsonPath('data.attributes.rating_count', 0)
        ->assertJsonPath('data.attributes.upcoming_dates', []);

    $tour = Tour::findOrFail($response->json('data.id'));

    expect(Str::isUlid($tour->id))->toBeTrue()
        ->and($tour->is_active)->toBeTrue()
        ->and($response->headers->get('Location'))->toBe(route('v1.tours.show', $tour));
});

it('creates an inactive tour with optional values', function () {
    $response = $this->postJson('/api/v1/tours', validTourPayload([
        'price_discount_percent' => 12.5,
        'description' => 'A complete tour description.',
        'is_active' => false,
    ]));

    $response
        ->assertCreated()
        ->assertJsonPath('data.attributes.price_discount_percent', '12.50')
        ->assertJsonPath('data.attributes.description', 'A complete tour description.');

    expect(Tour::findOrFail($response->json('data.id'))->is_active)->toBeFalse();
});

it('accepts zero-priced and fully discounted tours', function () {
    $this->postJson('/api/v1/tours', validTourPayload([
        'price' => 0,
        'price_discount_percent' => 100,
    ]))
        ->assertCreated()
        ->assertJsonPath('data.attributes.price', '0.00')
        ->assertJsonPath('data.attributes.price_discount_percent', '100.00');
});

it('generates unique slugs for duplicate names including soft-deleted tours', function () {
    $first = Tour::factory()->create([
        'name' => 'Alpine Adventure',
        'slug' => 'alpine-adventure',
    ]);
    $first->delete();

    $created = $this->postJson('/api/v1/tours', validTourPayload([
        'name' => 'Alpine Adventure',
    ]))
        ->assertCreated()
        ->json('data.attributes.slug');

    expect($created)->toBe('alpine-adventure-1');
});

it('rejects missing required create fields', function (string $field) {
    $this->postJson('/api/v1/tours', Arr::except(validTourPayload(), $field))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'name',
    'duration_days',
    'max_group_size',
    'difficulty',
    'price',
    'summary',
]);

it('rejects invalid create values', function (array $invalid, string $field) {
    $this->postJson('/api/v1/tours', validTourPayload($invalid))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'long name' => [['name' => str_repeat('a', 256)], 'name'],
    'zero duration' => [['duration_days' => 0], 'duration_days'],
    'oversized duration' => [['duration_days' => 256], 'duration_days'],
    'fractional duration' => [['duration_days' => 1.5], 'duration_days'],
    'zero group size' => [['max_group_size' => 0], 'max_group_size'],
    'oversized group size' => [['max_group_size' => 256], 'max_group_size'],
    'invalid difficulty' => [['difficulty' => 'extreme'], 'difficulty'],
    'negative price' => [['price' => -1], 'price'],
    'oversized price' => [['price' => 100000000], 'price'],
    'price precision' => [['price' => 10.999], 'price'],
    'zero discount' => [['price_discount_percent' => 0], 'price_discount_percent'],
    'oversized discount' => [['price_discount_percent' => 100.01], 'price_discount_percent'],
    'discount precision' => [['price_discount_percent' => 10.999], 'price_discount_percent'],
    'long summary' => [['summary' => str_repeat('a', 501)], 'summary'],
    'long description' => [['description' => str_repeat('a', 65536)], 'description'],
    'invalid active flag' => [['is_active' => 'yes'], 'is_active'],
]);

it('rejects unsupported and read-only create fields', function () {
    $this->postJson('/api/v1/tours', validTourPayload([
        'id' => (string) Str::ulid(),
        'slug' => 'client-slug',
        'rating_avg' => 5,
        'rating_count' => 10,
        'upcoming_dates' => [],
        'startDates' => [],
        'created_at' => now()->toIso8601String(),
        'unexpected' => true,
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'id',
            'slug',
            'rating_avg',
            'rating_count',
            'upcoming_dates',
            'startDates',
            'created_at',
            'unexpected',
        ]);
});

it('partially updates a tour while preserving server-managed and omitted data', function () {
    $tour = Tour::factory()->create([
        'name' => 'Original Tour',
        'slug' => 'original-tour',
        'duration_days' => 7,
        'price' => 500,
        'price_discount_percent' => 10,
        'description' => 'Original description.',
        'rating_avg' => 4.5,
        'rating_count' => 20,
        'is_active' => false,
    ]);
    $startDate = TourStartDate::factory()->for($tour)->create();

    $this->patchJson("/api/v1/tours/{$tour->id}", [
        'name' => 'Updated Tour',
        'price' => 650.25,
        'price_discount_percent' => null,
        'description' => null,
        'is_active' => true,
    ])
        ->assertOk()
        ->assertJsonPath('data.attributes.name', 'Updated Tour')
        ->assertJsonPath('data.attributes.slug', 'updated-tour')
        ->assertJsonPath('data.attributes.price', '650.25')
        ->assertJsonPath('data.attributes.price_discount_percent', null)
        ->assertJsonPath('data.attributes.description', null)
        ->assertJsonPath('data.attributes.rating_avg', '4.50')
        ->assertJsonPath('data.attributes.rating_count', 20);

    $tour->refresh();

    expect($tour->duration_days)->toBe(7)
        ->and($tour->is_active)->toBeTrue()
        ->and($tour->rating_avg)->toBe('4.50')
        ->and($tour->rating_count)->toBe(20)
        ->and(TourStartDate::find($startDate->id))->not->toBeNull();
});

it('rejects empty, invalid, and unsupported partial updates', function (array $payload, string $field) {
    $tour = Tour::factory()->create();

    $this->patchJson("/api/v1/tours/{$tour->id}", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'empty request' => [[], 'request'],
    'invalid enum' => [['difficulty' => 'extreme'], 'difficulty'],
    'invalid duration' => [['duration_days' => 0], 'duration_days'],
    'invalid price precision' => [['price' => 1.999], 'price'],
    'zero discount' => [['price_discount_percent' => 0], 'price_discount_percent'],
    'read-only rating' => [['rating_count' => 99], 'rating_count'],
    'nested start dates' => [['startDates' => []], 'startDates'],
    'unknown field' => [['unexpected' => true], 'unexpected'],
]);

it('returns not found when updating unknown, malformed, or deleted tours', function (string $identifier) {
    $this->patchJson("/api/v1/tours/{$identifier}", ['name' => 'Updated Tour'])
        ->assertNotFound();
})->with([
    'unknown ulid' => fn () => (string) Str::ulid(),
    'malformed identifier' => 'not-a-ulid',
    'soft-deleted tour' => function () {
        $tour = Tour::factory()->create();
        $tour->delete();

        return $tour->id;
    },
]);

it('registers post and patch writes without a put route', function () {
    expect(Route::getRoutes()->getByName('v1.tours.store'))->not->toBeNull()
        ->and(Route::getRoutes()->getByName('v1.tours.update')?->methods())->toBe(['PATCH']);

    $tour = Tour::factory()->create();

    $this->putJson("/api/v1/tours/{$tour->id}", validTourPayload())
        ->assertMethodNotAllowed();
});
