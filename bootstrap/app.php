<?php

use App\Http\Middleware\PreventResponseCaching;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->throttleApi('api');
        $middleware->trustHosts(
            at: static function (): array {
                $host = parse_url((string) config('app.url'), PHP_URL_HOST);

                return is_string($host) ? ['^'.preg_quote($host, '/').'$'] : [];
            },
            subdomains: false,
        );
        $middleware->alias([
            'no-store' => PreventResponseCaching::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
