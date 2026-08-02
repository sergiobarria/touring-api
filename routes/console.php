<?php

use App\Models\HealthCheckResultHistoryItem;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Spatie\Health\Commands\RunHealthChecksCommand;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('telescope:prune')->daily();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('auth:clear-resets')->everyFifteenMinutes();
Schedule::command(RunHealthChecksCommand::class)->everyMinute()->withoutOverlapping();
Schedule::command('model:prune', ['--model' => HealthCheckResultHistoryItem::class])->daily();
