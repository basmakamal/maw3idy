<?php

namespace App\Providers;

use App\Booking\Availability\SlotGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Support\ServiceProvider;

class BookingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The engine has no clock of its own; it is handed "now" so tests can
        // pin it and so travelTo() in feature tests is honoured.
        $this->app->bind(SlotGenerator::class, fn () => new SlotGenerator(CarbonImmutable::now()));
    }
}
