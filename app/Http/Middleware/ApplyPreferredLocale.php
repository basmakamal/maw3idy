<?php

namespace App\Http\Middleware;

use App\Support\Localization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the visitor's language choice on the central domain, where there is
 * no tenant to take a default from. Tenant subdomains do this inside
 * IdentifyTenant, which already knows the business's own language.
 */
final class ApplyPreferredLocale
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $chosen = Localization::chosen($request);

        if ($chosen !== null) {
            app()->setLocale($chosen);
        }

        return $next($request);
    }
}
