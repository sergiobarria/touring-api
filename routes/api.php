<?php

use Illuminate\Support\Facades\Route;

Route::get('health', \Spatie\Health\Http\Controllers\SimpleHealthCheckController::class);
// TODO: Apply auth middleware to the verbose health check route
Route::get('health/verbose', \Spatie\Health\Http\Controllers\HealthCheckJsonResultsController::class);

Route::prefix('v1')->group(base_path('routes/api_v1.php'));
