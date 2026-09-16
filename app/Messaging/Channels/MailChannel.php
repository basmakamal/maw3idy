<?php

namespace App\Messaging\Channels;

use App\Mail\CustomerNotificationMail;
use App\Messaging\Contracts\CustomerChannel;
use App\Messaging\CustomerNotification;
use App\Models\Booking;
use Illuminate\Support\Facades\Mail;

final class MailChannel implements CustomerChannel
{
    public function key(): string
    {
        return 'mail';
    }

    /**
     * A mailer is always available; "log" is a valid transport in development.
     */
    public function isConfigured(): bool
    {
        return filled(config('mail.default'));
    }

    public function canReach(Booking $booking): bool
    {
        return filled($booking->customer_email);
    }

    public function send(CustomerNotification $notification): void
    {
        // Sent inline: the surrounding job is already queued and retried.
        // The locale is pinned to the mail itself, because a Mailable renders
        // lazily and must not pick up whatever locale happens to be active then.
        Mail::to((string) $notification->booking->customer_email)
            ->locale($notification->booking->tenant->locale)
            ->send(new CustomerNotificationMail($notification));
    }
}
