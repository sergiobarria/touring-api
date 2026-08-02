<?php

use App\Enums\TourPermission;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\TourAnalyticsController;
use App\Http\Controllers\Api\V1\TourController;
use App\Http\Controllers\Api\V1\TourImageController;
use App\Http\Controllers\Api\V1\TourStartDateController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')
    ->name('auth.')
    ->controller(AuthController::class)
    ->group(function (): void {
        Route::post('register', 'register')->middleware(['throttle:registration', 'no-store'])->name('register');
        Route::post('login', 'login')->middleware(['throttle:login', 'no-store'])->name('login');
        Route::post('forgot-password', 'forgotPassword')->middleware(['throttle:password-email', 'no-store'])->name('password.email');
        Route::post('reset-password', 'resetPassword')->middleware(['throttle:password-reset', 'no-store'])->name('password.reset');
        Route::get('email/verify/{user}/{hash}', 'verifyEmail')->middleware(['signed', 'throttle:email-verification', 'no-store'])->name('verification.verify');
        Route::post('email/verification-notification', 'sendEmailVerification')->middleware(['auth:sanctum', 'throttle:email-verification', 'no-store'])->name('verification.send');
        Route::get('me', 'me')->middleware(['auth:sanctum', 'no-store'])->name('me');
        Route::patch('me', 'updateProfile')->middleware(['auth:sanctum', 'throttle:account-update', 'no-store'])->name('me.update');
        Route::put('password', 'updatePassword')->middleware(['auth:sanctum', 'throttle:account-update', 'no-store'])->name('password.update');
        Route::post('logout', 'logout')->middleware('auth:sanctum')->name('logout');
    });

Route::prefix('users')
    ->name('users.')
    ->controller(UserController::class)
    ->middleware(['auth:sanctum', 'no-store'])
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('{user}', 'show')->name('show');
        Route::patch('{user}/role', 'updateRole')->name('role.update');
        Route::delete('{user}', 'destroy')->name('destroy');
    });

Route::apiResource('tours', TourController::class)->only(['index', 'show']);
Route::post('tours', [TourController::class, 'store'])
    ->middleware(['auth:sanctum', 'can:'.TourPermission::CREATE->value])
    ->name('tours.store');
Route::patch('tours/{tour}', [TourController::class, 'update'])
    ->middleware(['auth:sanctum', 'can:'.TourPermission::UPDATE->value])
    ->name('tours.update');
Route::delete('tours/{tour}', [TourController::class, 'destroy'])
    ->middleware(['auth:sanctum', 'can:'.TourPermission::DELETE->value])
    ->name('tours.destroy');

Route::prefix('tours/{tour}/images')
    ->name('tours.images.')
    ->controller(TourImageController::class)
    ->middleware(['auth:sanctum', 'can:'.TourPermission::MANAGE_IMAGES->value])
    ->group(function (): void {
        Route::post('/', 'store')->name('store');
        Route::delete('{image}', 'destroy')->name('destroy');
    });

Route::prefix('tour-analytics')
    ->name('tour-analytics.')
    ->controller(TourAnalyticsController::class)
    ->middleware(['auth:sanctum', 'can:'.TourPermission::VIEW_ANALYTICS->value])
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
        Route::get('{tourStartDate}', 'show')->name('show');
        Route::middleware(['auth:sanctum', 'can:'.TourPermission::MANAGE_START_DATES->value])
            ->group(function (): void {
                Route::post('/', 'store')->name('store');
                Route::patch('{tourStartDate}', 'update')->name('update');
                Route::delete('{tourStartDate}', 'destroy')->name('destroy');
            });
    });
