<?php

namespace App\Providers;

use App\Tenancy\Resolvers\SubdomainTenantResolver;
use App\Tenancy\Resolvers\TenantResolver;
use App\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped, not singleton: Octane and queue workers reset it per request/job.
        $this->app->scoped(TenantContext::class);

        $this->app->bind(
            TenantResolver::class,
            fn () => new SubdomainTenantResolver((string) config('tenancy.central_domain')),
        );
    }

    public function boot(): void
    {
        // Reject hosts that cannot be a tenant before the router even tries the DB.
        Route::pattern('tenant', '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?');

        // Opening accounts is rare for a real person and cheap for a bot.
        RateLimiter::for('registration', fn (Request $request) => Limit::perHour(5)->by((string) $request->ip()));

        // Livewire re-hydrates components over its own endpoint. It must live on the
        // tenant subdomain and bind the tenant, or every component would fail closed.
        Livewire::setUpdateRoute(fn ($handle) => Route::post('/livewire/update', $handle)
            ->domain('{tenant}.'.config('tenancy.central_domain'))
            ->middleware(['web', 'tenant']));
    }
}
