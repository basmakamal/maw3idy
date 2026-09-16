<?php

namespace App\Messaging;

final class BookingCancelledNotification extends CustomerNotification
{
    public function key(): string
    {
        return 'booking.cancelled';
    }

    public function subject(): string
    {
        return __('Your booking at :business was cancelled', ['business' => $this->booking->tenant->name]);
    }

    public function heading(): string
    {
        return __('Booking :reference is cancelled.', ['reference' => $this->booking->reference]);
    }

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        $lines = [
            __('Service: :service', ['service' => $this->booking->service->name]),
            __('When: :when (:timezone)', ['when' => $this->when(), 'timezone' => $this->booking->tenant->timezone]),
        ];

        if (filled($this->booking->cancellation_reason)) {
            $lines[] = __('Reason: :reason', ['reason' => $this->booking->cancellation_reason]);
        }

        return $lines;
    }

    public function shortText(): string
    {
        return __(':business: booking :reference for :when has been cancelled.', [
            'business' => $this->booking->tenant->name,
            'reference' => $this->booking->reference,
            'when' => $this->when(),
        ]);
    }

    /**
     * Nothing left to manage.
     */
    public function actionLabel(): string
    {
        return __('Book again');
    }

    public function actionUrl(): string
    {
        return route('tenant.book', ['tenant' => $this->booking->tenant->slug]);
    }
}
