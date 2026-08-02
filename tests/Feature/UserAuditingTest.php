<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Users\UserRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OwenIt\Auditing\Models\Audit;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('audit.console', true);
});

it('audits user identity fields without credential values', function () {
    $user = User::factory()->create();
    $creation = Audit::query()
        ->where('auditable_type', User::class)
        ->where('auditable_id', $user->id)
        ->where('event', 'created')
        ->firstOrFail();

    expect($creation->new_values)->toHaveKeys(['name', 'email'])
        ->and($creation->new_values)->not->toHaveKeys(['password', 'remember_token']);

    $user->update(['name' => 'Updated Guide']);

    $update = Audit::query()
        ->where('auditable_type', User::class)
        ->where('auditable_id', $user->id)
        ->where('event', 'updated')
        ->latest('id')
        ->firstOrFail();

    expect($update->old_values['name'])->not->toBe('Updated Guide')
        ->and($update->new_values['name'])->toBe('Updated Guide');
});

it('audits password changes with a safe marker instead of either hash', function () {
    $user = User::factory()->create();
    $oldHash = $user->password;

    $user->update(['password' => 'a-new-secret-password']);

    $audit = Audit::query()
        ->where('auditable_type', User::class)
        ->where('auditable_id', $user->id)
        ->where('event', 'updated')
        ->latest('id')
        ->firstOrFail();

    expect($audit->new_values['password_changed'])->toBeTrue()
        ->and($audit->old_values)->not->toHaveKey('password')
        ->and($audit->new_values)->not->toHaveKey('password')
        ->and(json_encode([$audit->old_values, $audit->new_values]))->not->toContain($oldHash, $user->password);
});

it('audits role assignments with old and new role names', function () {
    $user = User::factory()->create();
    $roles = app(UserRoleService::class);

    $roles->assign($user, UserRole::GUIDE);
    $roles->assign($user, UserRole::LEAD_GUIDE);

    $audit = Audit::query()
        ->where('auditable_type', User::class)
        ->where('auditable_id', $user->id)
        ->where('tags', 'role-assignment')
        ->latest('id')
        ->firstOrFail();

    expect($audit->old_values['roles'])->toBe([UserRole::GUIDE->value])
        ->and($audit->new_values['roles'])->toBe([UserRole::LEAD_GUIDE->value]);
});
