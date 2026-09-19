<?php

namespace App\Providers;

use App\Exceptions\Booking\BookingNotChangeableException;
use App\Exceptions\Booking\SlotUnavailableException;
use App\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class ApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureRateLimits();
    }

    /**
     * Three different risks, three different limits: guessing a password,
     * scraping a public calendar, and a runaway integration.
     */
    private function configureRateLimits(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by((string) ($request->user()?->currentAccessToken()->id ?? $request->ip())));

        // Availability is the one endpoint anyone can call without a token: it
        // is cheap to request and expensive to answer, so it is keyed per
        // tenant and caller rather than globally.
        RateLimiter::for('api-public', fn (Request $request) => Limit::perMinute(60)
            ->by($this->tenantKey().':'.(string) $request->ip()));

        RateLimiter::for('api-tokens', fn (Request $request) => [
            Limit::perMinute(5)->by($this->tenantKey().':'.(string) $request->ip()),
            Limit::perMinute(5)->by($this->tenantKey().':'.strtolower((string) $request->input('email'))),
        ]);
    }

    private function tenantKey(): string
    {
        $context = $this->app->make(TenantContext::class);

        return $context->has() ? (string) $context->current()->getKey() : 'central';
    }

    /**
     * Domain failures that the API should answer with a status code rather
     * than a stack trace. Registered here so bootstrap/app.php stays readable.
     *
     * @return array<class-string, int>
     */
    public static function apiStatusCodes(): array
    {
        return [
            SlotUnavailableException::class => 409,
            BookingNotChangeableException::class => 422,
        ];
    }
}
