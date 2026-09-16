<?php

namespace App\Mail;

use App\Messaging\CustomerNotification;
use App\Support\Localization;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CustomerNotificationMail extends Mailable
{
    public function __construct(public readonly CustomerNotification $notification) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->notification->subject(),
            // Replies should reach the business, not the platform's no-reply address.
            replyTo: array_filter([$this->notification->booking->staff->email]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.customer-notification',
            with: [
                'notification' => $this->notification,
                'booking' => $this->notification->booking,
                'direction' => Localization::direction(app()->getLocale()),
            ],
        );
    }
}
