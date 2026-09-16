<?php

use App\Http\Middleware\IdentifyTenant;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Domain split
|--------------------------------------------------------------------------
|
| The central domain (landing, registration) and tenant subdomains are two
| separate route files. Nothing is registered without a domain, so a tenant
| route can never be reached from the central host or vice versa.
|
*/

Route::domain(config('tenancy.central_domain'))
    ->name('central.')
    ->group(base_path('routes/central.php'));

Route::domain('{tenant}.'.config('tenancy.central_domain'))
    ->middleware(IdentifyTenant::class)
    ->name('tenant.')
    ->group(base_path('routes/tenant.php'));
