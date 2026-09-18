<?php

namespace App\Listeners;

use App\Events\BookingRescheduled;
use App\Messaging\BookingRescheduledNotification;
use App\Messaging\CustomerNotifier;

final class SendRescheduleNotice
{
    public function __construct(private readonly CustomerNotifier $notifier) {}

    public function handle(BookingRescheduled $event): void
    {
        $this->notifier->send(new BookingRescheduledNotification($event->booking, $event->previousStart));
    }
}
