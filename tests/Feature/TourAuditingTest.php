<?php

use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use OwenIt\Auditing\Models\Audit;

uses(RefreshDatabase::class);

it('stores tour ulids as audit identifiers', function () {
    config()->set('audit.console', true);

    $tour = Tour::factory()->create();
    $audit = Audit::query()
        ->where('auditable_type', Tour::class)
        ->where('auditable_id', $tour->id)
        ->firstOrFail();

    expect($audit->auditable_id)->toBe($tour->id);
});

it('stores user ulids as audit actor identifiers', function () {
    config()->set('audit.console', true);

    $user = User::factory()->create();
    $this->actingAs($user);

    $tour = Tour::factory()->create();
    $audit = Audit::query()
        ->where('auditable_type', Tour::class)
        ->where('auditable_id', $tour->id)
        ->firstOrFail();

    expect(Str::isUlid($user->getKey()))->toBeTrue()
        ->and($audit->user_id)->toBe($user->id);
});
