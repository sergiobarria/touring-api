<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\TourController;

Route::get('health', \Spatie\Health\Http\Controllers\SimpleHealthCheckController::class);
// TODO: Apply auth middleware to the verbose health check route
Route::get('health/verbose', \Spatie\Health\Http\Controllers\HealthCheckJsonResultsController::class);

Route::prefix('v1')->group(function () {
    Route::apiResource('tours', TourController::class);
    Route::get('tours/slug/{slug:slug}', [TourController::class, 'getBySlug']);
});

