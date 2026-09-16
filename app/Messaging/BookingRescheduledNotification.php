<?php

namespace App\Messaging;

use App\Models\Booking;
use Carbon\CarbonImmutable;

final class BookingRescheduledNotification extends CustomerNotification
{
    public function __construct(Booking $booking, private readonly CarbonImmutable $previousStart)
    {
        parent::__construct($booking);
    }

    public function key(): string
    {
        return 'booking.rescheduled';
    }

    public function subject(): string
    {
        return __('Your booking at :business has moved', ['business' => $this->booking->tenant->name]);
    }

    public function heading(): string
    {
        return __('Booking :reference has a new time.', ['reference' => $this->booking->reference]);
    }

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        return [
            __('Service: :service', ['service' => $this->booking->service->name]),
            __('With: :staff', ['staff' => $this->booking->staff->name]),
            __('Previously: :when', ['when' => $this->previousWhen()]),
            __('Now: :when (:timezone)', ['when' => $this->when(), 'timezone' => $this->booking->tenant->timezone]),
        ];
    }

    public function shortText(): string
    {
        return __(':business: booking :reference moved from :old to :new.', [
            'business' => $this->booking->tenant->name,
            'reference' => $this->booking->reference,
            'old' => $this->previousWhen(),
            'new' => $this->when(),
        ]);
    }

    private function previousWhen(): string
    {
        $start = $this->previousStart->setTimezone($this->booking->tenant->timezone);

        return $start->translatedFormat('l j F Y').' · '.$start->format('H:i');
    }
}
