<?php

namespace App\Livewire\Booking;

use App\Actions\Booking\CreateBooking;
use App\Booking\Availability\AvailabilityService;
use App\Booking\Availability\AvailableSlot;
use App\Data\BookingRequestData;
use App\Exceptions\Booking\SlotUnavailableException;
use App\Models\Service;
use App\Models\Staff;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The public booking flow: service → staff (or anyone) → day → time → details → confirm.
 *
 * Nothing the browser sends is trusted: every id is re-fetched through the
 * tenant scope, every chosen time is re-derived from availability, and the
 * final insert happens under the staff member's row lock (CreateBooking).
 */
class BookingWizard extends Component
{
    private const MAX_BOOKINGS_PER_HOUR = 10;

    public int $step = 1;

    public ?int $serviceId = null;

    public ?int $staffId = null;

    public string $date = '';

    public ?string $slot = null;

    public string $customerName = '';

    public string $customerPhone = '';

    public string $customerEmail = '';

    public ?string $slotNotice = null;

    public function mount(): void
    {
        $this->date = $this->today()->toDateString();
    }

    public function chooseService(int $serviceId): void
    {
        $this->serviceId = $this->services()->firstWhere('id', $serviceId)->id
            ?? throw ValidationException::withMessages(['serviceId' => __('Please choose a service.')]);

        $this->staffId = null;
        $this->slot = null;
        $this->slotNotice = null;
        $this->step = 2;
    }

    public function chooseStaff(?int $staffId): void
    {
        if ($staffId !== null && $this->staffOptions()->firstWhere('id', $staffId) === null) {
            throw ValidationException::withMessages(['staffId' => __('Please choose a staff member.')]);
        }

        $this->staffId = $staffId;
        $this->slot = null;
        $this->step = 3;
    }

    public function updatedDate(): void
    {
        $this->slot = null;
        $this->slotNotice = null;

        $this->validateOnly('date');
    }

    public function chooseSlot(string $start): void
    {
        $chosen = CarbonImmutable::parse($start, 'UTC');

        if (! $this->slots()->contains(fn (AvailableSlot $slot) => $slot->start()->equalTo($chosen))) {
            throw ValidationException::withMessages(['slot' => __('That time is not available. Please choose another.')]);
        }

        $this->slot = $chosen->toIso8601String();
        $this->slotNotice = null;
        $this->step = 4;
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function confirm(CreateBooking $createBooking): void
    {
        $this->customerPhone = Phone::normalize($this->customerPhone);

        $validated = $this->validate();

        if ($this->slot === null || $this->serviceId === null) {
            $this->step = 3;

            return;
        }

        $throttleKey = 'book:'.tenant()->getKey().':'.(string) request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_BOOKINGS_PER_HOUR)) {
            throw ValidationException::withMessages(['customerPhone' => __('Too many bookings from this connection. Please try again later.')]);
        }

        try {
            $booking = $createBooking->handle(new BookingRequestData(
                serviceId: $this->serviceId,
                staffId: $this->staffId,
                start: CarbonImmutable::parse($this->slot, 'UTC'),
                customerName: $validated['customerName'],
                customerPhone: $validated['customerPhone'],
                customerEmail: $validated['customerEmail'] !== '' ? $validated['customerEmail'] : null,
            ));
        } catch (SlotUnavailableException) {
            $this->slot = null;
            $this->slotNotice = __('Sorry, that time was just taken. Please choose another.');
            $this->step = 3;

            return;
        }

        RateLimiter::hit($throttleKey, 3600);

        session()->put('confirmed_booking', $booking->getKey());

        $this->redirectRoute('tenant.book.confirmed');
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$this->today()->toDateString(), 'before_or_equal:'.$this->lastDay()->toDateString()],
            'customerName' => ['required', 'string', 'max:100'],
            'customerPhone' => ['required', 'string', 'regex:'.Phone::PATTERN],
            'customerEmail' => ['nullable', 'string', 'email:rfc', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'customerPhone.regex' => __('Enter a phone number with 8 to 15 digits, e.g. 05xxxxxxxx or +9665xxxxxxxx.'),
            'date.after_or_equal' => __('Please choose today or a later day.'),
            'date.before_or_equal' => __('Bookings are open up to :days days ahead.', ['days' => config('booking.window_days')]),
        ];
    }

    /**
     * @return EloquentCollection<int, Service>
     */
    #[Computed]
    public function services(): EloquentCollection
    {
        return Service::query()->active()->has('staff')->orderBy('name')->get();
    }

    #[Computed]
    public function service(): ?Service
    {
        return $this->serviceId === null ? null : $this->services()->firstWhere('id', $this->serviceId);
    }

    /**
     * @return EloquentCollection<int, Staff>
     */
    #[Computed]
    public function staffOptions(): EloquentCollection
    {
        $service = $this->service();

        return $service === null
            ? new EloquentCollection
            : $service->staff()->active()->orderBy('name')->get();
    }

    #[Computed]
    public function staff(): ?Staff
    {
        return $this->staffId === null ? null : $this->staffOptions()->firstWhere('id', $this->staffId);
    }

    /**
     * @return Collection<int, AvailableSlot>
     */
    #[Computed]
    public function slots(): Collection
    {
        $service = $this->service();

        if ($service === null || ! $this->dateIsWithinWindow()) {
            return new Collection;
        }

        return app(AvailabilityService::class)->slotsFor($service, $this->date, $this->staff());
    }

    public function timezone(): string
    {
        return tenant()->timezone;
    }

    public function render(): View
    {
        return view('livewire.booking.booking-wizard', [
            'minDate' => $this->today()->toDateString(),
            'maxDate' => $this->lastDay()->toDateString(),
        ]);
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(tenant()->timezone)->startOfDay();
    }

    private function lastDay(): CarbonImmutable
    {
        return $this->today()->addDays((int) config('booking.window_days'));
    }

    private function dateIsWithinWindow(): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date) !== 1) {
            return false;
        }

        $day = CarbonImmutable::parse($this->date, tenant()->timezone);

        return $day->between($this->today(), $this->lastDay());
    }
}
