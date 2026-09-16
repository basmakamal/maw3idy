<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Booking\BookingConfirmationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant subdomains
|--------------------------------------------------------------------------
|
| Served from {tenant}.central_domain. Every route here runs behind the
| IdentifyTenant middleware, so a tenant is always bound and route() can
| generate URLs without an explicit "tenant" parameter.
|
*/

Route::redirect('/', '/dashboard')->name('home');

/*
| Public booking page: no account needed.
*/
Route::view('/book', 'booking.book')->name('book');
Route::get('/book/confirmed', BookingConfirmationController::class)->name('book.confirmed');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        // Coarse per-IP ceiling on top of the per-account limiter in LoginRequest.
        ->middleware('throttle:20,1')
        ->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::view('/dashboard', 'dashboard.index')->name('dashboard');
    Route::view('/services', 'dashboard.services')->name('services');
    Route::view('/staff', 'dashboard.staff')->name('staff');
    Route::view('/calendar', 'dashboard.calendar')->name('calendar');
    Route::view('/settings', 'dashboard.settings')->name('settings');
});
