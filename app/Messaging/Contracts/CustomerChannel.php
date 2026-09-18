<?php

namespace App\Messaging\Contracts;

use App\Messaging\CustomerNotification;
use App\Models\Booking;

/**
 * A way of reaching a customer about their booking.
 *
 * Channels are transports, not messages: they receive a CustomerNotification
 * and decide how to render it. Adding SMS or push means one more class and one
 * more config entry, with nothing else in the application changing.
 */
interface CustomerChannel
{
    /**
     * Stable key used in config, logs and queued jobs.
     */
    public function key(): string;

    /**
     * Whether this channel has everything it needs to send at all
     * (credentials, a configured transport).
     */
    public function isConfigured(): bool;

    /**
     * Whether this particular customer can be reached this way
     * (an email address, a phone number).
     */
    public function canReach(Booking $booking): bool;

    public function send(CustomerNotification $notification): void;
}
