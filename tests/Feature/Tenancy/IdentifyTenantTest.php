<?php

use App\Http\Middleware\IdentifyTenant;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // A probe route that exposes what the middleware bound, independent of the app's own routes.
    Route::domain('{tenant}.'.config('tenancy.central_domain'))
        ->middleware(['web', IdentifyTenant::class])
        ->get('/_probe', fn () => response()->json([
            'tenant' => app(TenantContext::class)->current()->slug,
            'locale' => app()->getLocale(),
            'route_params' => request()->route()?->parameters(),
        ]))
        ->name('probe');

    // Routes registered after boot need their name index rebuilt before route() can find them.
    Route::getRoutes()->refreshNameLookups();
});

it('binds the tenant identified by the subdomain', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);

    $this->get(tenantUrl($tenant, '/_probe'))
        ->assertOk()
        ->assertJsonPath('tenant', 'acme')
        ->assertJsonPath('route_params', []);
});

it('returns 404 for an unknown subdomain', function () {
    $this->get('http://ghost.'.config('tenancy.central_domain').'/_probe')->assertNotFound();
});

it('returns 404 for nested or malformed subdomains', function () {
    Tenant::factory()->create(['slug' => 'acme']);

    $this->get('http://evil.acme.'.config('tenancy.central_domain').'/_probe')->assertNotFound();
    $this->get('http://ACME.'.config('tenancy.central_domain').'/_probe')->assertOk(); // hosts are case-insensitive
});

it('applies the tenant locale for the request', function () {
    $tenant = Tenant::factory()->arabic()->create();

    $this->get(tenantUrl($tenant, '/_probe'))->assertJsonPath('locale', 'ar');
});

it('generates tenant routes without an explicit tenant parameter', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);

    Route::domain('{tenant}.'.config('tenancy.central_domain'))
        ->middleware(['web', IdentifyTenant::class])
        ->get('/_link', fn () => route('probe'));

    $this->get(tenantUrl($tenant, '/_link'))
        ->assertOk()
        ->assertSee('http://acme.'.config('tenancy.central_domain').'/_probe');
});

it('forgets the tenant once the response has been sent', function () {
    $tenant = Tenant::factory()->create();

    $this->get(tenantUrl($tenant, '/_probe'))->assertOk();

    expect(app(TenantContext::class)->has())->toBeFalse();
});
