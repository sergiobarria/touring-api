<?php

use App\Http\Controllers\Api\V1\TourAnalyticsController;
use App\Http\Controllers\Api\V1\TourController;
use App\Http\Controllers\Api\V1\TourDateController;
use Illuminate\Support\Facades\Route;
use Spatie\Health\Http\Controllers\HealthCheckJsonResultsController;

Route::get('health', HealthCheckJsonResultsController::class);

Route::prefix('tours')->group(function () {
    Route::get('top', [TourAnalyticsController::class, 'getTopTours']);
    Route::get('stats', [TourAnalyticsController::class, 'getTourStats']);
    Route::get('monthly-plan/{year}', [TourAnalyticsController::class, 'getMonthlyPlan']);
});

Route::apiResource('tours', TourController::class);
Route::apiResource('tours.dates', TourDateController::class)->scoped(['date' => 'id']);
