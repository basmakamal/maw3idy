<?php

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

];
