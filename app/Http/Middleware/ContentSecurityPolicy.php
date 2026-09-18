<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * A Content-Security-Policy with a per-request nonce.
 *
 * The nonce is generated here and handed to Vite and Livewire, so the only
 * scripts the browser will run are the ones this response vouched for. See
 * ADR-021 for why `unsafe-eval` is still present and what it would take to
 * remove it.
 */
final class ContentSecurityPolicy
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Str::random(24);

        // Vite adds the nonce to the script and style tags it renders.
        Vite::useCspNonce($nonce);
        $request->attributes->set('csp_nonce', $nonce);

        $response = $next($request);

        // Only HTML is governed by a page policy; JSON and files are not.
        if (! $this->isHtml($response)) {
            return $response;
        }

        $header = config('security.csp_report_only') ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';

        $response->headers->set($header, $this->policy($nonce));

        return $response;
    }

    private function policy(string $nonce): string
    {
        $directives = [
            "default-src 'self'",
            // 'unsafe-eval': Alpine, which ships inside Livewire, compiles its
            // expressions with the Function constructor.
            sprintf("script-src 'self' 'nonce-%s' 'unsafe-eval'", $nonce),
            // Livewire and Vite both emit style tags; the nonce covers them,
            // and the font host serves the stylesheet for the web fonts.
            sprintf("style-src 'self' 'nonce-%s' https://fonts.bunny.net", $nonce),
            'font-src '."'self'".' https://fonts.bunny.net data:',
            "img-src 'self' data:",
            "connect-src 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "object-src 'none'",
        ];

        return implode('; ', $directives);
    }

    private function isHtml(Response $response): bool
    {
        return str_contains((string) $response->headers->get('Content-Type'), 'text/html');
    }
}
