<?php

namespace App\Providers;

use App\OpenApi\ConfigureTourWriteSchemas;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Scramble::ignoreDefaultRoutes();

        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Scramble Docs API versions
        Scramble::registerApi('v1', [
            'api_path' => 'api/v1',
            'info' => [
                'version' => '1.0.0',
            ],
        ])
            ->withDocumentTransformers(ConfigureTourWriteSchemas::class)
            ->expose(
                ui: 'docs/v1',
                document: 'docs/v1.json',
            );
    }
}
