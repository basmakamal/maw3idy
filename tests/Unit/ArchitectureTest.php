<?php

/*
 * Conventions enforced by the test suite rather than by code review:
 * no debugging leftovers, no insecure PHP functions, Laravel structure respected.
 */

arch()->preset()->php();

arch()->preset()->security();

arch()->preset()->laravel();

arch('models are Eloquent models and are only used by the application and database layers')
    ->expect('App\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model')
    ->toOnlyBeUsedIn(['App', 'Database']);

arch('middleware exposes a handle method')
    ->expect('App\Http\Middleware')
    ->toHaveMethod('handle');

/*
 * Tenant isolation is the property this product lives or dies by. Every model is
 * tenant-owned unless it is listed here as a deliberately central record.
 */
arch('every model is tenant scoped unless explicitly central')
    ->expect('App\Models')
    ->toUseTrait('App\Tenancy\Concerns\BelongsToTenant')
    ->ignoring('App\Models\Tenant');

arch('tenancy internals stay behind the trait and middleware')
    ->expect('App\Tenancy\Scopes\TenantScope')
    ->toOnlyBeUsedIn(['App\Tenancy']);
