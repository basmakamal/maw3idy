<?php

use App\Http\Controllers\Api\V1\AvailabilityController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\TokenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
|
| Served from each tenant's own subdomain, behind the same IdentifyTenant
| middleware as the web app, so one business's token can never read another
| business's data (ADR-018).
|
| Reads that a booking widget needs are public and throttled; anything that
| writes needs a token with the matching ability.
|
*/

Route::domain('{tenant}.'.config('tenancy.central_domain'))
    ->middleware('tenant')
    ->prefix('v1')
    ->name('api.v1.')
    ->group(function () {
        Route::post('/tokens', [TokenController::class, 'store'])
            ->middleware('throttle:api-tokens')
            ->name('tokens.store');

        Route::middleware('throttle:api-public')->group(function () {
            Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
            Route::get('/services/{service}', [ServiceController::class, 'show'])->name('services.show');
            Route::get('/services/{service}/availability', AvailabilityController::class)->name('services.availability');
        });

        Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
            Route::delete('/tokens/current', [TokenController::class, 'destroy'])->name('tokens.destroy');

            Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
            Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
            Route::get('/bookings/{booking:reference}', [BookingController::class, 'show'])->name('bookings.show');
            Route::delete('/bookings/{booking:reference}', [BookingController::class, 'destroy'])->name('bookings.destroy');
        });
    });
