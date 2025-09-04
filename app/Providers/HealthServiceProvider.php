<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DatabaseSizeCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
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
        Health::checks([
            EnvironmentCheck::new()->unless(app()->environment(['local', 'testing'])),
            DebugModeCheck::new()->unless(app()->environment(['local', 'staging'])),
            DatabaseCheck::new(),
            UsedDiskSpaceCheck::new()->unless(app()->environment(['local', 'staging'])),
            DatabaseSizeCheck::new()->failWhenSizeAboveGb(errorThresholdGb: 5.0),
            // ...other checks
        ]);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
