<?php

use App\Http\Controllers\Api\V1\TourController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::apiResource('tours', TourController::class);

Route::apiResource('users', UserController::class);
