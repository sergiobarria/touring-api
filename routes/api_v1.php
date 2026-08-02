<?php

use App\Http\Controllers\Api\V1\TourController;
use App\Http\Controllers\Api\V1\TourStartDateController;
use Illuminate\Support\Facades\Route;

Route::apiResource('tours', TourController::class)->only(['index', 'store', 'show', 'destroy']);
Route::patch('tours/{tour}', [TourController::class, 'update'])->name('tours.update');

Route::prefix('tours/{tour}/start-dates')
    ->name('tours.start-dates.')
    ->controller(TourStartDateController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('{tourStartDate}', 'show')->name('show');
        Route::patch('{tourStartDate}', 'update')->name('update');
        Route::delete('{tourStartDate}', 'destroy')->name('destroy');
    });
