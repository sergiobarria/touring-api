<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Spatie\Health\Commands\RunHealthChecksCommand;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

if (app()->environment('production')) {
    Schedule::command(RunHealthChecksCommand::class)->everyMinute();
} else {
    Schedule::command(RunHealthChecksCommand::class)->hourly();
}
