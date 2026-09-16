<?php

use App\Http\Controllers\Central\RegisterTenantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central domain
|--------------------------------------------------------------------------
|
| Served from config('tenancy.central_domain') only: the landing page and
| tenant registration. No tenant is ever bound here.
|
*/

Route::view('/', 'central.landing')->name('home');

Route::get('/register', [RegisterTenantController::class, 'create'])->name('register');
Route::post('/register', [RegisterTenantController::class, 'store'])
    ->middleware('throttle:registration')
    ->name('register.store');
