<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;

final class BookingCancelled
{
    use Dispatchable;

    public function __construct(
        public readonly Booking $booking,
        public readonly bool $byCustomer,
    ) {}
}
