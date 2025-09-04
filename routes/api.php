<?php

use Illuminate\Support\Facades\Route;
use Spatie\Health\Http\Controllers\HealthCheckJsonResultsController;

Route::get('health', HealthCheckJsonResultsController::class);

Route::prefix('v1')->group(fn() => require __DIR__ . '/api_v1.php');

Route::prefix('v2')->group(fn() => require __DIR__ . '/api_v2.php');

