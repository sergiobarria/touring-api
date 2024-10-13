<?php

use App\Http\Controllers\Api\V1\TourController;

Route::get('tours/top-rated', [TourController::class, 'getTopRatedTours']);
Route::get('tours/stats', [TourController::class, 'getTourStats']);
Route::get('tours/monthly-plan/{year}', [TourController::class, 'getMonthlyPlan'])
    ->where('year', '\d{4}'); // year must be 4 digits long, e.g. 2021, otherwise 404
Route::get('tours/slug/{slug:slug}', [TourController::class, 'getBySlug']);
Route::apiResource('tours', TourController::class);

