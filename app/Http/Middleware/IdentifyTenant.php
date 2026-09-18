<?php

namespace App\Http\Middleware;

use App\Support\Localization;
use App\Tenancy\Resolvers\TenantResolver;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant for the request and binds it for everything downstream:
 * the container, route URL generation and the application locale.
 *
 * Ordered before authentication (see bootstrap/app.php) so the user provider
 * already runs inside the tenant scope when it looks up the session's user.
 */
final class IdentifyTenant
{
    public function __construct(
        private readonly TenantResolver $resolver,
        private readonly TenantContext $context,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolver->resolve($request);

        if ($tenant === null) {
            abort(404);
        }

        $this->context->set($tenant);

        // Controllers never see the {tenant} route parameter, yet route() can
        // still generate tenant URLs without being told the subdomain each time.
        $route = $request->route();
        if ($route instanceof Route) {
            $route->forgetParameter('tenant');
        }
        URL::defaults(['tenant' => $tenant->slug]);

        // The tenant's language, unless this visitor asked for another one.
        app()->setLocale(Localization::chosen($request) ?? $tenant->locale);

        return $next($request);
    }

    /**
     * Never let a tenant outlive its request.
     */
    public function terminate(Request $request, Response $response): void
    {
        $this->context->forget();
    }
}
