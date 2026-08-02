<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\TourAnalyticsController;
use App\Http\Controllers\Api\V1\TourController;
use App\Http\Controllers\Api\V1\TourImageController;
use App\Http\Controllers\Api\V1\TourStartDateController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')
    ->name('auth.')
    ->controller(AuthController::class)
    ->group(function (): void {
        Route::post('register', 'register')->middleware('throttle:5,1')->name('register');
        Route::post('login', 'login')->name('login');
        Route::post('logout', 'logout')->middleware('auth:sanctum')->name('logout');
    });

Route::apiResource('tours', TourController::class)->only(['index', 'store', 'show', 'destroy']);
Route::patch('tours/{tour}', [TourController::class, 'update'])->name('tours.update');

Route::prefix('tours/{tour}/images')
    ->name('tours.images.')
    ->controller(TourImageController::class)
    ->group(function (): void {
        Route::post('/', 'store')->name('store');
        Route::delete('{image}', 'destroy')->name('destroy');
    });

Route::prefix('tour-analytics')
    ->name('tour-analytics.')
    ->controller(TourAnalyticsController::class)
    ->group(function (): void {
        Route::get('top-tours', 'topTours')->name('top-tours');
        Route::get('stats', 'stats')->name('stats');
        Route::get('monthly-plan/{year}', 'monthlyPlan')->name('monthly-plan');
    });

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
