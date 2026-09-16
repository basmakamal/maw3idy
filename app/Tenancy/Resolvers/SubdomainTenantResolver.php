<?php

namespace App\Tenancy\Resolvers;

use App\Models\Tenant;
use Illuminate\Http\Request;

final class SubdomainTenantResolver implements TenantResolver
{
    /**
     * A single DNS label: lowercase letters, digits, hyphens; 1–63 chars.
     */
    public const SLUG_PATTERN = '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/';

    public function __construct(private readonly string $centralDomain) {}

    public function resolve(Request $request): ?Tenant
    {
        $slug = $this->slugFromHost($request->getHost());

        if ($slug === null) {
            return null;
        }

        return Tenant::query()->where('slug', $slug)->first();
    }

    /**
     * Extract the tenant label from a host such as "acme.maw3idy.test".
     * Returns null for the central domain, nested subdomains and foreign hosts.
     */
    public function slugFromHost(string $host): ?string
    {
        $host = strtolower($host);
        $suffix = '.'.strtolower($this->centralDomain);

        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        $slug = substr($host, 0, -strlen($suffix));

        return preg_match(self::SLUG_PATTERN, $slug) === 1 ? $slug : null;
    }
}
