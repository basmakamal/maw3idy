<?php

namespace App\Messaging;

use Carbon\CarbonImmutable;

final class BookingReminderNotification extends CustomerNotification
{
    public function key(): string
    {
        return 'booking.reminder';
    }

    /**
     * A booking cancelled or already past by the time the queue gets here
     * must not produce a reminder.
     */
    public function stillRelevant(): bool
    {
        return $this->booking->isConfirmed()
            && $this->booking->starts_at->greaterThan(CarbonImmutable::now());
    }

    public function subject(): string
    {
        return __('Reminder: your appointment at :business', ['business' => $this->booking->tenant->name]);
    }

    public function heading(): string
    {
        return __('See you tomorrow, :name.', ['name' => $this->booking->customer_name]);
    }

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        return [
            __('Service: :service', ['service' => $this->booking->service->name]),
            __('With: :staff', ['staff' => $this->booking->staff->name]),
            __('When: :when (:timezone)', ['when' => $this->when(), 'timezone' => $this->booking->tenant->timezone]),
            __('Reference: :reference', ['reference' => $this->booking->reference]),
        ];
    }

    public function shortText(): string
    {
        return __('Reminder from :business: :service with :staff on :when. Reference :reference.', [
            'business' => $this->booking->tenant->name,
            'service' => $this->booking->service->name,
            'staff' => $this->booking->staff->name,
            'when' => $this->when(),
            'reference' => $this->booking->reference,
        ]);
    }
}
