<?php

namespace App\Messaging;

use App\Jobs\SendCustomerNotification;
use Illuminate\Support\Facades\Log;

/**
 * Decides which channels should carry a message and queues one job per
 * channel, so a failing transport retries on its own without re-sending the
 * ones that already succeeded.
 */
final class CustomerNotifier
{
    public function __construct(private readonly ChannelRegistry $registry) {}

    /**
     * @return list<string> the channel keys that were queued
     */
    public function send(CustomerNotification $notification): array
    {
        // Load what every message needs while a tenant is still bound, so the
        // queued copy carries it and no worker has to lazy-load mid-render.
        $notification->booking->loadMissing(['tenant', 'service', 'staff']);

        $queued = [];

        foreach ($this->registry->all() as $key => $channel) {
            if (! $channel->isConfigured() || ! $channel->canReach($notification->booking)) {
                continue;
            }

            SendCustomerNotification::dispatch($notification, $key);
            $queued[] = $key;
        }

        if ($queued === []) {
            Log::info('No channel could deliver a customer notification', [
                'notification' => $notification->key(),
                'booking' => $notification->booking->getKey(),
            ]);
        }

        return $queued;
    }
}
