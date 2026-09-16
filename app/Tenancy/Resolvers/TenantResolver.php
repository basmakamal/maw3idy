<?php

namespace App\Tenancy\Resolvers;

use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * Strategy for working out which tenant a request belongs to.
 *
 * The web app resolves by subdomain; the API (Phase 4) can bind a header- or
 * token-based implementation without touching the middleware.
 */
interface TenantResolver
{
    public function resolve(Request $request): ?Tenant;
}
