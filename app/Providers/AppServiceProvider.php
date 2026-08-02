<?php

namespace App\Providers;

use App\Models\User;
use App\OpenApi\ConfigureTourWriteSchemas;
use Dedoc\Scramble\Scramble;
use Illuminate\Auth\Notifications\ResetPassword;
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
        ResetPassword::createUrlUsing(fn (User $user, string $token): string => rtrim((string) config('app.frontend_url'), '/').'/reset-password?'.http_build_query([
            'token' => $token,
            'email' => $user->email,
        ]));
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
