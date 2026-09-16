<?php

namespace App\Tenancy;

use App\Exceptions\Tenancy\TenantNotBoundException;
use App\Models\Tenant;
use Closure;

/**
 * Holds the tenant for the current request, job or command.
 *
 * Registered as a scoped singleton so long-running workers (Octane, queue
 * workers) get a fresh instance per unit of work and can never leak a tenant
 * from one request into the next.
 */
final class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function forget(): void
    {
        $this->tenant = null;
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    public function find(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * @throws TenantNotBoundException
     */
    public function current(): Tenant
    {
        return $this->tenant ?? throw TenantNotBoundException::noContext();
    }

    /**
     * Run a callback with the given tenant bound, restoring whatever was bound
     * before. This is the only sanctioned way to act on behalf of a tenant from
     * the central domain, a console command or a queued job.
     *
     * @template TReturn
     *
     * @param  Closure(Tenant): TReturn  $callback
     * @return TReturn
     */
    public function runAs(Tenant $tenant, Closure $callback): mixed
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;

        try {
            return $callback($tenant);
        } finally {
            $this->tenant = $previous;
        }
    }
}
