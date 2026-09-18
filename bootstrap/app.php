<?php

use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\SecurityHeaders;
use App\Providers\ApiServiceProvider;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);

        $middleware->alias(['tenant' => IdentifyTenant::class]);

        // The tenant must be bound before authentication resolves the session's
        // user (the user provider runs inside the tenant scope) and before route
        // model binding resolves tenant-owned models.
        $middleware->prependToPriorityList(AuthenticatesRequests::class, IdentifyTenant::class);

        // Auth lives on tenant subdomains; route() fills in the subdomain from the bound tenant.
        $middleware->redirectGuestsTo(fn () => route('tenant.login'));
        $middleware->redirectUsersTo(fn () => route('tenant.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Domain failures are answers, not crashes: the API says 409 when a
        // slot went while the caller was deciding, and 422 when a booking is
        // no longer changeable.
        $exceptions->render(function (DomainException $e, Request $request) {
            $status = ApiServiceProvider::apiStatusCodes()[$e::class] ?? null;

            if ($status === null || ! $request->expectsJson()) {
                return null;
            }

            return response()->json(['message' => $e->getMessage()], $status);
        });
    })->create();
