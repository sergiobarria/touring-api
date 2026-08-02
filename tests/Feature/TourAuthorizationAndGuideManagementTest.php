<?php

use App\Enums\TourPermission;
use App\Enums\UserRole;
use App\Models\Tour;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TourGuideSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OwenIt\Auditing\Models\Audit;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
});

function tourGuidePayload(User $leadGuide, array $overrides = []): array
{
    return array_replace([
        'name' => 'Guide Team Tour',
        'lead_guide_id' => $leadGuide->id,
        'guide_ids' => [],
        'duration_days' => 5,
        'max_group_size' => 12,
        'difficulty' => 'moderate',
        'price' => 499,
        'summary' => 'A tour used to verify guide team management.',
    ], $overrides);
}

it('grants every tour permission only to admins', function (UserRole $role) {
    $user = tourUserWithRole($role);

    foreach (TourPermission::cases() as $permission) {
        expect($user->can($permission->value))->toBe($role === UserRole::ADMIN);
    }
})->with(UserRole::cases());

it('seeds a stable development guide pool idempotently', function () {
    $this->seed(TourGuideSeeder::class);
    $this->seed(TourGuideSeeder::class);

    expect(User::role(UserRole::LEAD_GUIDE->value)->count())->toBe(TourGuideSeeder::LEAD_GUIDE_COUNT)
        ->and(User::role(UserRole::GUIDE->value)->count())->toBe(TourGuideSeeder::GUIDE_COUNT);
});

it('keeps catalog reads public while protecting writes and analytics', function () {
    $tour = Tour::factory()->create();

    $this->getJson('/api/v1/tours')->assertOk();
    $this->getJson("/api/v1/tours/{$tour->id}")->assertOk();
    $this->postJson('/api/v1/tours', [])->assertUnauthorized();
    $this->getJson('/api/v1/tour-analytics/stats')->assertUnauthorized();

    $this->actingAs(tourUserWithRole(UserRole::GUIDE));
    $this->postJson('/api/v1/tours', [])->assertForbidden();
    $this->getJson('/api/v1/tour-analytics/stats')->assertForbidden();
});

it('creates and atomically replaces a valid guide team', function () {
    config()->set('audit.console', true);
    $this->actingAs(tourUserWithRole(UserRole::ADMIN));
    $lead = tourUserWithRole(UserRole::LEAD_GUIDE);
    $replacementLead = tourUserWithRole(UserRole::LEAD_GUIDE);
    $guides = collect(range(1, 3))->map(fn () => tourUserWithRole(UserRole::GUIDE));

    $response = $this->postJson('/api/v1/tours', tourGuidePayload($lead, [
        'guide_ids' => $guides->take(2)->pluck('id')->all(),
    ]))->assertCreated();

    $response
        ->assertJsonPath('data.attributes.lead_guide.id', $lead->id)
        ->assertJsonCount(2, 'data.attributes.guides')
        ->assertJsonMissing(['email' => $lead->email]);

    $tour = Tour::query()->findOrFail($response->json('data.id'));

    $this->patchJson("/api/v1/tours/{$tour->id}", [
        'lead_guide_id' => $replacementLead->id,
        'guide_ids' => [$guides->last()->id],
    ])->assertOk()
        ->assertJsonPath('data.attributes.lead_guide.id', $replacementLead->id)
        ->assertJsonCount(1, 'data.attributes.guides');

    expect($tour->fresh()->lead_guide_id)->toBe($replacementLead->id)
        ->and($tour->fresh()->guides()->pluck('users.id')->all())->toBe([$guides->last()->id]);

    $assignmentAudit = Audit::query()->where('tags', 'guide-assignments')->latest('id')->firstOrFail();

    expect($assignmentAudit->user_id)->toBe(auth()->id())
        ->and($assignmentAudit->old_values['guide_ids'])->toHaveCount(2)
        ->and($assignmentAudit->new_values['guide_ids'])->toBe([$guides->last()->id]);
});

it('rejects invalid guide roles duplicates and oversized teams', function () {
    $this->actingAs(tourUserWithRole(UserRole::ADMIN));
    $lead = tourUserWithRole(UserRole::LEAD_GUIDE);
    $guide = tourUserWithRole(UserRole::GUIDE);
    $ordinaryUser = tourUserWithRole(UserRole::USER);

    $this->postJson('/api/v1/tours', tourGuidePayload($guide))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('lead_guide_id');

    $this->postJson('/api/v1/tours', tourGuidePayload($lead, ['guide_ids' => [$ordinaryUser->id]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('guide_ids.0');

    $this->postJson('/api/v1/tours', tourGuidePayload($lead, ['guide_ids' => [$guide->id, $guide->id]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('guide_ids.1');

    $fiveGuides = collect(range(1, 5))->map(fn () => tourUserWithRole(UserRole::GUIDE))->pluck('id')->all();
    $this->postJson('/api/v1/tours', tourGuidePayload($lead, ['guide_ids' => $fiveGuides]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('guide_ids');
});

it('prevents deleting or reroling an assigned guide', function () {
    $admin = tourUserWithRole(UserRole::ADMIN);
    $lead = tourUserWithRole(UserRole::LEAD_GUIDE);
    $guide = tourUserWithRole(UserRole::GUIDE);
    Tour::factory()->withGuideTeam($lead, [$guide])->create();

    $this->actingAs($admin)
        ->deleteJson("/api/v1/users/{$guide->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('user');

    $this->actingAs($admin)
        ->patchJson("/api/v1/users/{$lead->id}/role", ['role' => UserRole::GUIDE->value])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role');
});
