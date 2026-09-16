<?php

namespace App\Livewire\Dashboard;

use App\Actions\Booking\CancelBooking;
use App\Booking\Availability\Period;
use App\Enums\Weekday;
use App\Exceptions\Booking\BookingNotChangeableException;
use App\Models\Booking;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The business's diary: a day or a week at a time, optionally for one staff
 * member. Bookings are stored as UTC instants and read back in the tenant's
 * timezone, so the day boundaries here are the tenant's, not the server's.
 */
class Calendar extends Component
{
    #[Url(as: 'view', keep: true)]
    public string $mode = 'week';

    #[Url(as: 'date', keep: true)]
    public string $date = '';

    #[Url(as: 'staff', keep: true)]
    public ?int $staffId = null;

    public bool $includeCancelled = false;

    public ?string $notice = null;

    public ?string $problem = null;

    public function mount(): void
    {
        if (! $this->isValidDate($this->date)) {
            $this->date = $this->today()->toDateString();
        }

        if (! in_array($this->mode, ['day', 'week'], true)) {
            $this->mode = 'week';
        }

        // A staff id from the query string still has to belong to this tenant.
        if ($this->staffId !== null && $this->staffOptions()->firstWhere('id', $this->staffId) === null) {
            $this->staffId = null;
        }
    }

    public function showDay(?string $date = null): void
    {
        if ($date !== null && $this->isValidDate($date)) {
            $this->date = $date;
        }

        $this->mode = 'day';
        $this->clearMessages();
    }

    public function showWeek(): void
    {
        $this->mode = 'week';
        $this->clearMessages();
    }

    public function goToToday(): void
    {
        $this->date = $this->today()->toDateString();
        $this->clearMessages();
    }

    public function previous(): void
    {
        $this->shift(-1);
    }

    public function next(): void
    {
        $this->shift(1);
    }

    public function updatedDate(): void
    {
        if (! $this->isValidDate($this->date)) {
            $this->date = $this->today()->toDateString();
        }

        $this->clearMessages();
    }

    public function updatedStaffId(): void
    {
        $this->clearMessages();
    }

    public function cancel(int $bookingId, CancelBooking $cancelBooking): void
    {
        $booking = Booking::query()->findOrFail($bookingId);

        $this->authorize('cancel', $booking);

        try {
            $cancelBooking->handle($booking, __('Cancelled by the business'));
        } catch (BookingNotChangeableException) {
            $this->problem = __('That booking could not be cancelled. It may already be cancelled or in the past.');

            return;
        }

        $this->notice = __('Booking :reference cancelled. The customer has been told.', ['reference' => $booking->reference]);
        $this->problem = null;

        unset($this->bookings);
    }

    /**
     * @return EloquentCollection<int, Staff>
     */
    #[Computed(persist: false)]
    public function staffOptions(): EloquentCollection
    {
        return Staff::query()->orderBy('name')->get();
    }

    /**
     * The bookings in the visible range, keyed by the tenant-local day they
     * fall on, so the views can render columns without re-filtering.
     *
     * @return Collection<string, Collection<int, Booking>>
     */
    #[Computed(persist: false)]
    public function bookings(): Collection
    {
        $range = $this->range();
        $timezone = $this->timezone();

        $bookings = Booking::query()
            ->with(['service', 'staff'])
            ->when(! $this->includeCancelled, fn ($query) => $query->confirmed())
            ->when($this->staffId !== null, fn ($query) => $query->where('staff_id', $this->staffId))
            ->where('starts_at', '>=', $range->start)
            ->where('starts_at', '<', $range->end)
            ->orderBy('starts_at')
            ->get();

        /** @var Collection<string, Collection<int, Booking>> $byDay */
        $byDay = (new Collection($bookings->all()))
            ->groupBy(fn (Booking $booking) => $booking->starts_at->setTimezone($timezone)->toDateString());

        return $byDay;
    }

    /**
     * @return list<CarbonImmutable> the days shown, in the tenant's week order
     */
    public function days(): array
    {
        $anchor = $this->anchor();

        if ($this->mode === 'day') {
            return [$anchor];
        }

        $start = $this->weekStart($anchor);

        return array_map(fn (int $offset) => $start->addDays($offset), range(0, 6));
    }

    public function timezone(): string
    {
        return tenant()->timezone;
    }

    public function heading(): string
    {
        $days = $this->days();

        if ($this->mode === 'day') {
            return $days[0]->translatedFormat('l j F Y');
        }

        $first = $days[0];
        $last = $days[6];

        return $first->month === $last->month
            ? $first->translatedFormat('j').' – '.$last->translatedFormat('j F Y')
            : $first->translatedFormat('j F').' – '.$last->translatedFormat('j F Y');
    }

    public function isToday(CarbonImmutable $day): bool
    {
        return $day->toDateString() === $this->today()->toDateString();
    }

    public function render(): View
    {
        return view('livewire.dashboard.calendar', [
            'days' => $this->days(),
            'timezone' => $this->timezone(),
            'currency' => config('booking.currency'),
        ]);
    }

    /**
     * The visible range as a UTC period, ready for the query.
     */
    private function range(): Period
    {
        $days = $this->days();

        return new Period(
            $days[0]->startOfDay()->utc(),
            $days[count($days) - 1]->startOfDay()->addDay()->utc(),
        );
    }

    private function anchor(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->date, $this->timezone())->startOfDay();
    }

    /**
     * Weeks start on Sunday here, matching how the region reads a calendar.
     */
    private function weekStart(CarbonImmutable $day): CarbonImmutable
    {
        return $day->subDays(Weekday::of($day) === Weekday::Sunday ? 0 : Weekday::of($day)->value);
    }

    private function shift(int $direction): void
    {
        $this->date = $this->mode === 'day'
            ? $this->anchor()->addDays($direction)->toDateString()
            : $this->weekStart($this->anchor())->addWeeks($direction)->toDateString();

        $this->clearMessages();
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone())->startOfDay();
    }

    private function isValidDate(?string $date): bool
    {
        return $date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1;
    }

    private function clearMessages(): void
    {
        $this->notice = null;
        $this->problem = null;
    }
}
