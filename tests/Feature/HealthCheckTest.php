<?php

use App\Models\HealthCheckResultHistoryItem;
use App\Providers\HealthServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Checks\Result;
use Spatie\Health\Commands\RunHealthChecksCommand;
use Spatie\Health\Facades\Health;
use Spatie\Health\ResultStores\EloquentHealthResultStore;
use Spatie\Health\ResultStores\ResultStore;

uses(RefreshDatabase::class);

test('the liveness endpoint remains available', function () {
    $this->get('/up')->assertOk();
});

test('the health endpoint reports a healthy database and stores the result', function () {
    Artisan::call(RunHealthChecksCommand::class);

    $this->getJson('/health')
        ->assertOk()
        ->assertExactJson(['healthy' => true]);

    $historyItem = HealthCheckResultHistoryItem::query()->sole();

    expect(Str::isUlid($historyItem->getKey()))->toBeTrue()
        ->and(HealthCheckResultHistoryItem::query()->count())->toBe(1)
        ->and(config('health.result_stores.'.EloquentHealthResultStore::class.'.keep_history_for_days'))->toBe(7)
        ->and(config('health.notifications.enabled'))->toBeFalse();
});

test('the health endpoint returns an opaque service unavailable response for a failed check', function () {
    Health::fake([
        DatabaseCheck::class => Result::make()->failed('Sensitive database diagnostic'),
    ]);
    Artisan::call(RunHealthChecksCommand::class);

    $this->getJson('/health')
        ->assertServiceUnavailable()
        ->assertExactJson(['healthy' => false])
        ->assertDontSee('Sensitive database diagnostic');
});

test('the health endpoint normalizes result store errors without exposing diagnostics', function () {
    $this->mock(ResultStore::class, function (MockInterface $mock) {
        $mock->shouldReceive('latestResults')
            ->once()
            ->andThrow(new RuntimeException('Sensitive store diagnostic'));
    });

    $this->getJson('/health')
        ->assertServiceUnavailable()
        ->assertExactJson(['healthy' => false])
        ->assertDontSee('Sensitive store diagnostic');
});

test('the public health endpoint does not execute or persist fresh checks', function () {
    Artisan::call(RunHealthChecksCommand::class);

    $this->getJson('/health?fresh')->assertOk();

    expect(HealthCheckResultHistoryItem::query()->count())->toBe(1);
});

test('the health endpoint rejects stale scheduled results', function () {
    Artisan::call(RunHealthChecksCommand::class);
    HealthCheckResultHistoryItem::query()->update(['created_at' => now()->subMinutes(3)]);

    $this->getJson('/health')
        ->assertServiceUnavailable()
        ->assertExactJson(['healthy' => false]);
});

test('only the database check is registered outside production', function () {
    expect(Health::registeredChecks()->map(fn ($check) => $check::class)->all())
        ->toBe([DatabaseCheck::class]);
});

test('production registers the operational checks', function () {
    Health::clearChecks();
    app()->instance('env', 'production');

    (new HealthServiceProvider(app()))->boot();

    expect(Health::registeredChecks()->map(fn ($check) => $check::class)->all())
        ->toBe([
            DatabaseCheck::class,
            UsedDiskSpaceCheck::class,
            EnvironmentCheck::class,
            DebugModeCheck::class,
        ]);
});

test('health checks are scheduled every minute without overlapping', function () {
    $events = collect(app(Schedule::class)->events());
    $healthCheck = $events->first(fn ($event) => str_contains($event->command ?? '', 'health:check'));
    $historyPrune = $events->first(fn ($event) => str_contains($event->command ?? '', 'model:prune'));

    expect($healthCheck)->not->toBeNull()
        ->and($healthCheck->expression)->toBe('* * * * *')
        ->and($healthCheck->withoutOverlapping)->toBeTrue()
        ->and($historyPrune)->not->toBeNull()
        ->and($historyPrune->command)->toContain(HealthCheckResultHistoryItem::class)
        ->and($historyPrune->expression)->toBe('0 0 * * *');
});
