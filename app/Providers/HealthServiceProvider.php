<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Facades\Health;

class HealthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $checks = [
            DatabaseCheck::new(),
        ];

        if ($this->app->environment('production')) {
            $checks = [
                ...$checks,
                UsedDiskSpaceCheck::new(),
                EnvironmentCheck::new(),
                DebugModeCheck::new(),
            ];
        }

        Health::checks($checks);
    }
}
