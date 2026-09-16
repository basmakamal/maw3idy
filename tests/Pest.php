<?php

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Tenancy helpers
|--------------------------------------------------------------------------
|
| Feature tests address tenants the way browsers do: by host. These helpers
| build the right URLs and bind a tenant when a test exercises the domain
| layer directly instead of going through the IdentifyTenant middleware.
|
*/

function centralUrl(string $path = '/'): string
{
    return 'http://'.config('tenancy.central_domain').'/'.ltrim($path, '/');
}

function tenantUrl(Tenant $tenant, string $path = '/'): string
{
    return 'http://'.$tenant->slug.'.'.config('tenancy.central_domain').'/'.ltrim($path, '/');
}

/**
 * Bind a tenant the way a real request on its subdomain would, for tests that
 * bypass HTTP (domain code, Livewire component tests): tenant in the container,
 * route() defaults, and the tenant host as the root for relative URLs (Livewire's
 * test harness posts updates to a relative /livewire/update).
 */
function bindTenant(Tenant $tenant): Tenant
{
    app(TenantContext::class)->set($tenant);
    URL::defaults(['tenant' => $tenant->slug]);
    URL::forceRootUrl('http://'.$tenant->domain());

    return $tenant;
}
