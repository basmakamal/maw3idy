<?php

namespace App\Messaging;

use App\Models\Booking;
use Carbon\CarbonImmutable;

/**
 * Something the business tells a customer about one booking.
 *
 * Subclasses provide the wording; channels decide how to deliver it. Text is
 * built when the notification is rendered, inside the tenant's locale, so the
 * same class produces Arabic or English without knowing which.
 */
abstract class CustomerNotification
{
    public function __construct(public readonly Booking $booking) {}

    /**
     * Stable key for logs and queued jobs.
     */
    abstract public function key(): string;

    abstract public function subject(): string;

    abstract public function heading(): string;

    /**
     * @return list<string>
     */
    abstract public function lines(): array;

    /**
     * One paragraph for channels without layout, such as WhatsApp.
     */
    abstract public function shortText(): string;

    /**
     * Whether the message is still worth sending when the job finally runs.
     * A booking cancelled between queueing and sending should stay quiet.
     */
    public function stillRelevant(): bool
    {
        return true;
    }

    public function actionLabel(): ?string
    {
        return __('View or change your booking');
    }

    public function actionUrl(): ?string
    {
        return $this->booking->manageUrl();
    }

    /**
     * The appointment in the tenant's timezone, for display.
     */
    protected function localStart(): CarbonImmutable
    {
        return $this->booking->starts_at->setTimezone($this->booking->tenant->timezone);
    }

    protected function when(): string
    {
        $start = $this->localStart();

        return $start->translatedFormat('l j F Y').' · '.$start->format('H:i');
    }
}
