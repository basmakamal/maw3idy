<?php

namespace App\Providers;

use App\Booking\Availability\SlotGenerator;
use App\Messaging\ChannelRegistry;
use App\Messaging\Contracts\CustomerChannel;
use App\Messaging\Contracts\WhatsAppGateway;
use App\Messaging\Gateways\LogWhatsAppGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\ServiceProvider;

/**
 * Booking-side bindings. Event listeners are not wired here: Laravel discovers
 * every `handle()` in app/Listeners, and registering them again would make
 * each one fire twice.
 */
class BookingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The engine has no clock of its own; it is handed "now" so tests can
        // pin it and so travelTo() in feature tests is honoured.
        $this->app->bind(SlotGenerator::class, fn () => new SlotGenerator(CarbonImmutable::now()));

        // Swap this binding for a real Cloud API client to turn WhatsApp on.
        $this->app->bind(WhatsAppGateway::class, LogWhatsAppGateway::class);

        // Bound, not shared: the enabled set follows config, which tests and
        // future per-tenant settings need to be able to change at runtime.
        $this->app->bind(ChannelRegistry::class, function (): ChannelRegistry {
            /** @var array<string, array{class: class-string<CustomerChannel>, enabled: bool}> $configured */
            $configured = config('booking.channels', []);

            $channels = [];

            foreach ($configured as $key => $channel) {
                if (! $channel['enabled']) {
                    continue;
                }

                $channels[$key] = $this->app->make($channel['class']);
            }

            return new ChannelRegistry($channels);
        });
    }
}
