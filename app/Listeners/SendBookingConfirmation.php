<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Messaging\BookingConfirmedNotification;
use App\Messaging\CustomerNotifier;

/**
 * BookingCreated is dispatched after its transaction commits, so by the time
 * this runs the booking is durable and safe to queue work for.
 */
final class SendBookingConfirmation
{
    public function __construct(private readonly CustomerNotifier $notifier) {}

    public function handle(BookingCreated $event): void
    {
        $this->notifier->send(new BookingConfirmedNotification($event->booking));
    }
}
