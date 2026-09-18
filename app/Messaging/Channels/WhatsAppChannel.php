<?php

namespace App\Messaging\Channels;

use App\Messaging\Contracts\CustomerChannel;
use App\Messaging\Contracts\WhatsAppGateway;
use App\Messaging\CustomerNotification;
use App\Models\Booking;
use App\Support\Phone;

/**
 * Deliberately a stub: the channel, its gateway contract and its wiring are
 * real and tested, but the only gateway in this repository logs instead of
 * calling the WhatsApp Business API. Enabling it is a config flag plus one
 * class, which is the point of the abstraction.
 */
final class WhatsAppChannel implements CustomerChannel
{
    public function __construct(private readonly WhatsAppGateway $gateway) {}

    public function key(): string
    {
        return 'whatsapp';
    }

    public function isConfigured(): bool
    {
        return (bool) config('booking.channels.whatsapp.enabled', false);
    }

    /**
     * WhatsApp needs an international number; a national format such as
     * 05xxxxxxxx cannot be dialled without knowing the country.
     */
    public function canReach(Booking $booking): bool
    {
        $phone = Phone::normalize($booking->customer_phone);

        return str_starts_with($phone, '+') && Phone::isValid($phone);
    }

    public function send(CustomerNotification $notification): void
    {
        $this->gateway->send(
            Phone::normalize($notification->booking->customer_phone),
            $notification->shortText(),
        );
    }
}
