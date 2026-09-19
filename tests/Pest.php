<?php

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTruncation;
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
| Concurrency tests spawn extra PHP processes that must see the fixtures, so
| they commit for real and truncate afterwards instead of rolling back.
*/
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->in('Concurrency');

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

function apiUrl(Tenant $tenant, string $path = '/'): string
{
    return tenantUrl($tenant, '/api/v1'.$path);
}

/**
 * Cross the boundary between two real requests.
 *
 * A real request resolves the authenticated user from scratch and binds its
 * own tenant; the test client keeps the auth guard alive between calls and
 * IdentifyTenant deliberately forgets the tenant when a response is sent. Call
 * this between requests in one test, passing the tenant when the assertions
 * afterwards query tenant-owned models.
 */
function betweenRequests(?Tenant $tenant = null): void
{
    auth()->forgetGuards();

    if ($tenant !== null) {
        bindTenant($tenant);
    }
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
