<?php

namespace App\Jobs;

use App\Messaging\ChannelRegistry;
use App\Messaging\CustomerNotification;
use App\Models\Booking;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Delivers one customer message over one channel.
 *
 * A queue worker has no request and therefore no tenant, so the job restores
 * the booking's tenant before touching anything (ADR-014) and switches the
 * application locale to that tenant's language before the text is built.
 */
class SendCustomerNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Space the retries out: a mail host that just refused us is unlikely to
     * accept the same message a second later.
     *
     * @var list<int>
     */
    public array $backoff = [10, 60];

    public function __construct(
        private readonly CustomerNotification $notification,
        private readonly string $channelKey,
    ) {}

    public function handle(ChannelRegistry $registry, TenantContext $context): void
    {
        $booking = $this->notification->booking;

        $context->runAs($booking->tenant, function () use ($registry, $booking): void {
            $previousLocale = app()->getLocale();
            app()->setLocale($booking->tenant->locale);

            try {
                $booking->loadMissing(['tenant', 'service', 'staff']);

                $this->deliver($registry, $booking);
            } finally {
                app()->setLocale($previousLocale);
            }
        });
    }

    private function deliver(ChannelRegistry $registry, Booking $booking): void
    {
        if (! $this->notification->stillRelevant()) {
            Log::info('Skipped a customer notification that no longer applies', [
                'notification' => $this->notification->key(),
                'booking' => $booking->getKey(),
            ]);

            return;
        }

        $registry->get($this->channelKey)->send($this->notification);
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return [
            'notification:'.$this->notification->key(),
            'channel:'.$this->channelKey,
            'booking:'.$this->notification->booking->getKey(),
        ];
    }
}
