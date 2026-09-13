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
