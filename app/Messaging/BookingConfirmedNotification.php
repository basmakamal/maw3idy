<?php

namespace App\Messaging;

final class BookingConfirmedNotification extends CustomerNotification
{
    public function key(): string
    {
        return 'booking.confirmed';
    }

    public function subject(): string
    {
        return __('Your booking at :business is confirmed', ['business' => $this->booking->tenant->name]);
    }

    public function heading(): string
    {
        return __('You\'re booked, :name.', ['name' => $this->booking->customer_name]);
    }

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        return [
            __('Reference: :reference', ['reference' => $this->booking->reference]),
            __('Service: :service', ['service' => $this->booking->service->name]),
            __('With: :staff', ['staff' => $this->booking->staff->name]),
            __('When: :when (:timezone)', ['when' => $this->when(), 'timezone' => $this->booking->tenant->timezone]),
        ];
    }

    public function shortText(): string
    {
        return __(':business: booking :reference confirmed for :when. :service with :staff.', [
            'business' => $this->booking->tenant->name,
            'reference' => $this->booking->reference,
            'when' => $this->when(),
            'service' => $this->booking->service->name,
            'staff' => $this->booking->staff->name,
        ]);
    }
}
