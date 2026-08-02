<?php

use App\Http\Controllers\Api\V1\TourController;
use Illuminate\Support\Facades\Route;

Route::apiResource('tours', TourController::class)->only(['index', 'store', 'show', 'destroy']);
Route::patch('tours/{tour}', [TourController::class, 'update'])->name('tours.update');
