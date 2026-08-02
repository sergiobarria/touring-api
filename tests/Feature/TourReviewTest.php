<?php

use App\Enums\UserRole;
use App\Models\Review;
use App\Models\Tour;
use App\Models\User;
use App\Services\Tours\TourRatingService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ReviewSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

it('registers public reads and authenticated review writes without put', function () {
    foreach (['index', 'store', 'show', 'update', 'destroy'] as $action) {
        expect(Route::getRoutes()->getByName("v1.tours.reviews.{$action}"))->not->toBeNull();
    }

    expect(Route::getRoutes()->getByName('v1.tours.reviews.update')?->methods())->toBe(['PATCH']);

    $tour = Tour::factory()->create();
    $review = Review::factory()->for($tour)->create();

    $this->getJson("/api/v1/tours/{$tour->id}/reviews")->assertOk();
    $this->getJson("/api/v1/tours/{$tour->id}/reviews/{$review->id}")->assertOk();
    $this->postJson("/api/v1/tours/{$tour->id}/reviews", [
        'rating' => 5,
        'review' => 'Excellent.',
    ])->assertUnauthorized();
    $this->putJson("/api/v1/tours/{$tour->id}/reviews/{$review->id}", [])->assertMethodNotAllowed();
});

it('creates one review per user for active or inactive tours and updates aggregates', function (bool $active) {
    $user = User::factory()->create();
    $tour = Tour::factory()->create(['is_active' => $active]);
    $this->actingAs($user);

    $response = $this->postJson("/api/v1/tours/{$tour->id}/reviews", [
        'rating' => 4,
        'review' => '  A memorable experience.  ',
    ]);

    $response->assertCreated()->assertHeader('Location')
        ->assertJsonPath('data.type', 'reviews')
        ->assertJsonPath('data.attributes.rating', 4)
        ->assertJsonPath('data.attributes.review', 'A memorable experience.')
        ->assertJsonPath('data.attributes.author.id', $user->id)
        ->assertJsonPath('data.attributes.author.name', $user->name);

    expect($tour->fresh()->rating_avg)->toBe('4.00')
        ->and($tour->fresh()->rating_count)->toBe(1);

    $this->postJson("/api/v1/tours/{$tour->id}/reviews", [
        'rating' => 5,
        'review' => 'Again.',
    ])->assertUnprocessable()->assertJsonValidationErrors('review');
})->with([true, false]);

it('validates review writes and rejects unsupported or empty payloads', function () {
    $user = User::factory()->create();
    $tour = Tour::factory()->create();
    $this->actingAs($user);

    $url = "/api/v1/tours/{$tour->id}/reviews";
    $this->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors(['rating', 'review']);
    $this->postJson($url, ['rating' => 0, 'review' => ' '])
        ->assertUnprocessable()->assertJsonValidationErrors(['rating', 'review']);
    $this->postJson($url, ['rating' => 6, 'review' => 'Text', 'user_id' => $user->id])
        ->assertUnprocessable()->assertJsonValidationErrors(['rating', 'user_id']);

    $review = Review::factory()->for($tour)->for($user)->create();
    $this->patchJson("{$url}/{$review->id}", [])->assertUnprocessable()->assertJsonValidationErrors('review');
});

it('lists reviews newest first with pagination and returns author data', function () {
    $tour = Tour::factory()->create(['is_active' => false]);
    $old = Review::factory()->for($tour)->create(['created_at' => now()->subDay()]);
    $new = Review::factory()->for($tour)->create(['created_at' => now()]);

    $this->getJson("/api/v1/tours/{$tour->id}/reviews?per_page=1&page=1")
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $new->id)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.per_page', 1);
    $this->getJson("/api/v1/tours/{$tour->id}/reviews?per_page=1&page=2")
        ->assertOk()->assertJsonPath('data.0.id', $old->id);
});

it('allows only the owner to update and delete a nested review', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $tour = Tour::factory()->create();
    $otherTour = Tour::factory()->create();
    $review = Review::factory()->for($tour)->for($owner)->create(['rating' => 2]);
    app(TourRatingService::class)->recompute($tour);

    $this->actingAs($other);
    $this->patchJson("/api/v1/tours/{$tour->id}/reviews/{$review->id}", ['rating' => 5])->assertForbidden();
    $this->patchJson("/api/v1/tours/{$tour->id}/reviews/{$review->id}", ['rating' => 99])->assertForbidden();
    $this->deleteJson("/api/v1/tours/{$tour->id}/reviews/{$review->id}")->assertForbidden();
    $this->getJson("/api/v1/tours/{$otherTour->id}/reviews/{$review->id}")->assertNotFound();

    $this->actingAs($owner);
    $this->patchJson("/api/v1/tours/{$tour->id}/reviews/{$review->id}", ['rating' => 5])
        ->assertOk()->assertJsonPath('data.attributes.rating', 5);
    expect($tour->fresh()->rating_avg)->toBe('5.00');

    $this->deleteJson("/api/v1/tours/{$tour->id}/reviews/{$review->id}")->assertNoContent();
    expect($tour->fresh()->rating_avg)->toBeNull()->and($tour->fresh()->rating_count)->toBe(0);

    $this->postJson("/api/v1/tours/{$tour->id}/reviews", ['rating' => 3, 'review' => 'Replacement.'])
        ->assertCreated();
});

it('recomputes exact aggregates after create update text-only update and delete', function () {
    $tour = Tour::factory()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();

    $this->actingAs($first)->postJson("/api/v1/tours/{$tour->id}/reviews", [
        'rating' => 4, 'review' => 'First.',
    ])->assertCreated();
    $secondResponse = $this->actingAs($second)->postJson("/api/v1/tours/{$tour->id}/reviews", [
        'rating' => 5, 'review' => 'Second.',
    ])->assertCreated();

    expect($tour->fresh()->rating_avg)->toBe('4.50')->and($tour->fresh()->rating_count)->toBe(2);

    $reviewId = $secondResponse->json('data.id');
    $this->patchJson("/api/v1/tours/{$tour->id}/reviews/{$reviewId}", ['review' => 'Updated text.'])->assertOk();
    expect($tour->fresh()->rating_avg)->toBe('4.50');

    $this->patchJson("/api/v1/tours/{$tour->id}/reviews/{$reviewId}", ['rating' => 2])->assertOk();
    expect($tour->fresh()->rating_avg)->toBe('3.00');
});

it('removes a deleted users reviews and recomputes affected tours', function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    $admin = tourUserWithRole(UserRole::ADMIN);
    $user = tourUserWithRole(UserRole::USER);
    $other = User::factory()->create();
    $tour = Tour::factory()->create();
    Review::factory()->for($tour)->for($user)->create(['rating' => 1]);
    Review::factory()->for($tour)->for($other)->create(['rating' => 5]);
    app(TourRatingService::class)->recompute($tour);
    $deletedTour = Tour::factory()->create();
    Review::factory()->for($deletedTour)->for($user)->create(['rating' => 3]);
    app(TourRatingService::class)->recompute($deletedTour);
    $deletedTour->delete();

    $this->actingAs($admin)->deleteJson("/api/v1/users/{$user->id}")->assertNoContent();

    $deletedTour = Tour::withTrashed()->findOrFail($deletedTour->id);

    expect(Review::query()->where('user_id', $user->id)->exists())->toBeFalse()
        ->and($tour->fresh()->rating_avg)->toBe('5.00')
        ->and($tour->fresh()->rating_count)->toBe(1)
        ->and($deletedTour->rating_avg)->toBeNull()
        ->and($deletedTour->rating_count)->toBe(0);
});

it('hides reviews when their parent tour is soft deleted', function () {
    $tour = Tour::factory()->create();
    $review = Review::factory()->for($tour)->create();
    $tour->delete();

    $this->getJson("/api/v1/tours/{$tour->id}/reviews")->assertNotFound();
    $this->getJson("/api/v1/tours/{$tour->id}/reviews/{$review->id}")->assertNotFound();
    expect(Review::find($review->id))->not->toBeNull();
});

it('seeds regular users and coherent distinct reviews for each tour', function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);
    Tour::factory()->count(2)->create();
    $this->seed(ReviewSeeder::class);

    expect(User::role(UserRole::USER->value)->count())->toBe(UserSeeder::USER_COUNT);

    Tour::query()->each(function (Tour $tour): void {
        $reviews = $tour->reviews;

        expect($reviews->count())->toBeGreaterThanOrEqual(3)
            ->toBeLessThanOrEqual(10)
            ->and($reviews->pluck('user_id')->unique()->count())->toBe($reviews->count())
            ->and($tour->rating_count)->toBe($reviews->count())
            ->and((float) $tour->rating_avg)->toBe(round((float) $reviews->avg('rating'), 2));
    });
});
