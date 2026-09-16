<?php

use App\Messaging\Channels\MailChannel;
use App\Messaging\Channels\WhatsAppChannel;

return [

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | ISO 4217 code used when displaying prices. Stored prices are plain
    | decimals; the currency is a display concern until billing exists.
    |
    */

    'currency' => env('BOOKING_CURRENCY', 'SAR'),

    /*
    |--------------------------------------------------------------------------
    | Booking window
    |--------------------------------------------------------------------------
    |
    | How many days ahead a customer may book on the public page.
    |
    */

    'window_days' => (int) env('BOOKING_WINDOW_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Slot grid
    |--------------------------------------------------------------------------
    |
    | Minutes between offered start times. Null means "the service's own
    | duration", i.e. back-to-back slots; 15 gives a finer grid.
    |
    */

    'slot_interval_minutes' => env('BOOKING_SLOT_INTERVAL') !== null ? (int) env('BOOKING_SLOT_INTERVAL') : null,

    /*
    |--------------------------------------------------------------------------
    | Customer self-service
    |--------------------------------------------------------------------------
    |
    | How close to the appointment a customer may still cancel or move it
    | themselves. Inside that window they are asked to call the business, and
    | the staff can still cancel from the dashboard.
    |
    */

    'cancellation_notice_hours' => (int) env('BOOKING_CANCELLATION_NOTICE_HOURS', 2),

    /*
    |--------------------------------------------------------------------------
    | Reminders
    |--------------------------------------------------------------------------
    |
    | Reminders are found by a scheduled query, not by delayed jobs: a queue
    | flush or a redeploy cannot lose them, and a booking cancelled in the
    | meantime is simply never reminded. See ADR-015.
    |
    */

    'reminder_hours_before' => (int) env('BOOKING_REMINDER_HOURS_BEFORE', 24),

    /*
    |--------------------------------------------------------------------------
    | Customer notification channels
    |--------------------------------------------------------------------------
    |
    | Each channel decides for itself whether it is configured and whether it
    | can reach a given customer. One queued job per channel per message, so a
    | failing transport retries alone. WhatsApp ships as a stub: the channel
    | and its gateway contract are real, only the HTTP client is missing.
    |
    */

    'channels' => [
        'mail' => [
            'class' => MailChannel::class,
            'enabled' => (bool) env('BOOKING_MAIL_ENABLED', true),
        ],
        'whatsapp' => [
            'class' => WhatsAppChannel::class,
            'enabled' => (bool) env('BOOKING_WHATSAPP_ENABLED', false),
        ],
    ],

];
