<?php

namespace App\Livewire\Booking;

use App\Actions\Booking\CancelBooking;
use App\Actions\Booking\RescheduleBooking;
use App\Booking\Availability\AvailabilityService;
use App\Booking\Availability\AvailableSlot;
use App\Exceptions\Booking\BookingNotChangeableException;
use App\Exceptions\Booking\SlotUnavailableException;
use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Lets a customer see, move or cancel their own booking.
 *
 * The token is the only authority here, and it is #[Locked] so a tampered
 * payload cannot point the component at someone else's booking. Every action
 * re-reads the booking by that token, so nothing is trusted between requests.
 */
class ManageBooking extends Component
{
    #[Locked]
    public string $token = '';

    public bool $choosingTime = false;

    public string $date = '';

    public string $reason = '';

    public ?string $notice = null;

    public ?string $problem = null;

    public function mount(string $token): void
    {
        $this->token = $token;

        $booking = $this->booking();

        if ($booking === null) {
            abort(404);
        }

        $this->date = $booking->starts_at->setTimezone($this->timezone())->toDateString();
    }

    #[Computed(persist: false)]
    public function booking(): ?Booking
    {
        return Booking::query()
            ->with(['service', 'staff', 'tenant'])
            ->where('cancel_token', $this->token)
            ->first();
    }

    public function startReschedule(): void
    {
        $this->choosingTime = true;
        $this->problem = null;
    }

    public function stopReschedule(): void
    {
        $this->choosingTime = false;
    }

    public function chooseSlot(string $start, RescheduleBooking $reschedule): void
    {
        $booking = $this->booking() ?? abort(404);

        try {
            $reschedule->handle($booking, CarbonImmutable::parse($start, 'UTC'), byCustomer: true);
        } catch (SlotUnavailableException) {
            $this->problem = __('Sorry, that time was just taken. Please choose another.');
            unset($this->booking, $this->slots);

            return;
        } catch (BookingNotChangeableException $e) {
            $this->problem = $this->explain($e);

            return;
        }

        $this->choosingTime = false;
        $this->problem = null;
        $this->notice = __('Your booking has been moved. We\'ve sent you the new details.');

        unset($this->booking, $this->slots);
    }

    public function cancel(CancelBooking $cancelBooking): void
    {
        $booking = $this->booking() ?? abort(404);

        $this->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $cancelBooking->handle($booking, $this->reason !== '' ? $this->reason : null, byCustomer: true);
        } catch (BookingNotChangeableException $e) {
            $this->problem = $this->explain($e);

            return;
        }

        $this->choosingTime = false;
        $this->problem = null;
        $this->notice = __('Your booking has been cancelled.');

        unset($this->booking, $this->slots);
    }

    /**
     * @return Collection<int, AvailableSlot>
     */
    #[Computed(persist: false)]
    public function slots(): Collection
    {
        $booking = $this->booking();

        if ($booking === null || ! $booking->isConfirmed() || ! $this->dateIsWithinWindow()) {
            return new Collection;
        }

        return app(AvailabilityService::class)->slotsFor(
            $booking->service,
            $this->date,
            $booking->staff,
            excluding: $booking,
        );
    }

    public function timezone(): string
    {
        return $this->booking()?->tenant->timezone ?? config('app.timezone');
    }

    public function canChange(): bool
    {
        $booking = $this->booking();

        if ($booking === null || ! $booking->isConfirmed()) {
            return false;
        }

        $noticeHours = (int) config('booking.cancellation_notice_hours');

        return $booking->starts_at->greaterThan(CarbonImmutable::now()->addHours($noticeHours));
    }

    public function render(): View
    {
        $today = CarbonImmutable::now($this->timezone())->startOfDay();

        return view('livewire.booking.manage-booking', [
            'minDate' => $today->toDateString(),
            'maxDate' => $today->addDays((int) config('booking.window_days'))->toDateString(),
            'noticeHours' => (int) config('booking.cancellation_notice_hours'),
        ]);
    }

    private function explain(BookingNotChangeableException $exception): string
    {
        return match ($exception->getMessage()) {
            'This booking has already been cancelled.' => __('This booking has already been cancelled.'),
            'This booking is in the past.' => __('This booking is in the past.'),
            default => __('This booking is too close to its start time to change online. Please call the business.'),
        };
    }

    private function dateIsWithinWindow(): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date) !== 1) {
            return false;
        }

        $timezone = $this->timezone();
        $today = CarbonImmutable::now($timezone)->startOfDay();

        return CarbonImmutable::parse($this->date, $timezone)
            ->between($today, $today->addDays((int) config('booking.window_days')));
    }
}
