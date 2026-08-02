<?php

use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
