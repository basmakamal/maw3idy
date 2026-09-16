<?php

use App\Http\Middleware\IdentifyTenant;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RouteInstance;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

it('registers a single Livewire update endpoint, on tenant subdomains, behind IdentifyTenant', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RouteInstance $route) => $route->uri() === 'livewire/update');

    expect($routes)->toHaveCount(1);

    /** @var RouteInstance $route */
    $route = $routes->first();

    expect($route->getDomain())->toBe('{tenant}.'.config('tenancy.central_domain'))
        ->and(app('router')->resolveMiddleware($route->gatherMiddleware()))
        ->toContain(IdentifyTenant::class)
        ->toContain(StartSession::class);
});

it('matches Livewire updates on a tenant host but not on the central domain', function () {
    $tenant = Tenant::factory()->create();
    $routes = Route::getRoutes();

    $matched = $routes->match(Request::create(tenantUrl($tenant, '/livewire/update'), 'POST'));
    expect($matched->uri())->toBe('livewire/update');

    expect(fn () => $routes->match(Request::create(centralUrl('/livewire/update'), 'POST')))
        ->toThrow(NotFoundHttpException::class);
});
