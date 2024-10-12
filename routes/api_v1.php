<?php

use App\Http\Controllers\Api\V1\TourController;

Route::apiResource('tours', TourController::class);
Route::get('tours/slug/{slug:slug}', [TourController::class, 'getBySlug']);
