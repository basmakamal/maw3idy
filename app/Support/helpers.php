<?php

use App\Exceptions\Tenancy\TenantNotBoundException;
use App\Models\Tenant;
use App\Tenancy\TenantContext;

if (! function_exists('tenant')) {
    /**
     * The tenant bound to the current request, job or command.
     *
     * @throws TenantNotBoundException
     */
    function tenant(): Tenant
    {
        return app(TenantContext::class)->current();
    }
}
