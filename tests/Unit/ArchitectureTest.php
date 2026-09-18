<?php

use App\Mail\CustomerNotificationMail;

/*
 * Conventions enforced by the test suite rather than by code review:
 * no debugging leftovers, no insecure PHP functions, Laravel structure respected.
 */

arch()->preset()->php();

arch()->preset()->security();

arch()->preset()->laravel()->ignoring([
    // The preset wants Mailables to be queueable. This one is queued one level
    // up, by SendCustomerNotification, which runs one job per channel so each
    // transport retries alone; making the Mailable queueable too would add a
    // second hop and split one message's retries across two jobs.
    CustomerNotificationMail::class,
]);

arch('models are Eloquent models and are only used by the application and database layers')
    ->expect('App\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model')
    ->toOnlyBeUsedIn(['App', 'Database']);

arch('middleware exposes a handle method')
    ->expect('App\Http\Middleware')
    ->toHaveMethod('handle');

/*
 * Tenant isolation is the property this product lives or dies by. Every model is
 * tenant-owned unless it is listed here as a deliberately central record.
 */
arch('every model is tenant scoped unless explicitly central')
    ->expect('App\Models')
    ->toUseTrait('App\Tenancy\Concerns\BelongsToTenant')
    ->ignoring('App\Models\Tenant');

arch('tenancy internals stay behind the trait and middleware')
    ->expect('App\Tenancy\Scopes\TenantScope')
    ->toOnlyBeUsedIn(['App\Tenancy']);

/*
 * Customer messages are ours, not Illuminate notifications: a customer is a
 * name and a phone number on a booking row, never a Notifiable model.
 */
arch('every customer message is a CustomerNotification')
    ->expect('App\Messaging')
    ->toExtend('App\Messaging\CustomerNotification')
    ->ignoring([
        'App\Messaging\ChannelRegistry',
        'App\Messaging\Channels',
        'App\Messaging\Contracts',
        'App\Messaging\CustomerNotifier',
        'App\Messaging\Gateways',
    ]);

arch('every delivery channel implements the channel contract')
    ->expect('App\Messaging\Channels')
    ->toImplement('App\Messaging\Contracts\CustomerChannel');
