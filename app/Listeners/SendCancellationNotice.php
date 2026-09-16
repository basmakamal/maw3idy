<?php

namespace App\Listeners;

use App\Events\BookingCancelled;
use App\Messaging\BookingCancelledNotification;
use App\Messaging\CustomerNotifier;

final class SendCancellationNotice
{
    public function __construct(private readonly CustomerNotifier $notifier) {}

    public function handle(BookingCancelled $event): void
    {
        $this->notifier->send(new BookingCancelledNotification($event->booking));
    }
}
