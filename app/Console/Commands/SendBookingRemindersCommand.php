<?php

namespace App\Console\Commands;

use App\Messaging\BookingReminderNotification;
use App\Messaging\CustomerNotifier;
use App\Models\Booking;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Finds the bookings whose reminder is due and queues it.
 *
 * Reminders are a scheduled query rather than a delayed job (ADR-015): a queue
 * flush, a redeploy or an hour of downtime cannot lose them, and a booking
 * cancelled in the meantime is simply skipped. Claiming a reminder with a
 * conditional UPDATE keeps it at-most-once even if two runs overlap.
 */
class SendBookingRemindersCommand extends Command
{
    protected $signature = 'bookings:send-reminders';

    protected $description = 'Queue reminders for bookings that start soon';

    public function handle(CustomerNotifier $notifier, TenantContext $context): int
    {
        $now = CarbonImmutable::now();
        $threshold = $now->addHours((int) config('booking.reminder_hours_before'));

        /** @var EloquentCollection<int, Booking> $due */
        $due = Booking::withoutTenancy()
            ->confirmed()
            ->whereNull('reminder_sent_at')
            ->where('starts_at', '>', $now)
            ->where('starts_at', '<=', $threshold)
            ->with(['tenant', 'service', 'staff'])
            ->orderBy('starts_at')
            ->get();

        $sent = 0;

        foreach ($due as $booking) {
            // Claim it first: whoever wins this UPDATE owns the reminder.
            $claimed = Booking::withoutTenancy()
                ->whereKey($booking->getKey())
                ->whereNull('reminder_sent_at')
                ->update(['reminder_sent_at' => $now->format('Y-m-d H:i:s')]);

            if ($claimed !== 1) {
                continue;
            }

            $context->runAs($booking->tenant, function () use ($notifier, $booking, &$sent): void {
                $notifier->send(new BookingReminderNotification($booking));
                $sent++;
            });
        }

        $this->info(sprintf('%d reminder(s) queued.', $sent));

        return self::SUCCESS;
    }
}
