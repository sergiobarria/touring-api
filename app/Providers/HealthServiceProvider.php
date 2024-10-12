<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Facades\Health;

class HealthServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Common health checks
        Health::checks([
            // UsedDiskSpaceCheck::new(),
            DatabaseCheck::new(),
        ]);

        // Checks that should only run in production
        if ($this->app->environment('production')) {
            Health::checks([
                EnvironmentCheck::new()->expectEnvironment('production'),
                UsedDiskSpaceCheck::new(),
            ]);
        }
    }
}
