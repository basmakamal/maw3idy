<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after the booking's transaction has committed, so listeners
 * (confirmation notifications in Phase 3) never see a row that may roll back.
 */
final class BookingCreated
{
    use Dispatchable;

    public function __construct(public readonly Booking $booking) {}
}
