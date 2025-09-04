<?php

use Spatie\Health\Commands\RunHealthChecksCommand;

Schedule::command('telescope:prune')->daily();

if (app()->environment() == 'production') {
    Schedule::command(RunHealthChecksCommand::class)->everyMinute();
} else {
    Schedule::command(RunHealthChecksCommand::class)->everyFifteenMinutes();
}
