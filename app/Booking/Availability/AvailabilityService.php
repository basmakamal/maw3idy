<?php

namespace App\Booking\Availability;

use App\Models\Booking;
use App\Models\Service;
use App\Models\Staff;
use App\Models\TimeOff;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Loads what the engine needs from the database for the bound tenant and asks
 * SlotGenerator for the answer. This is the only place availability touches
 * Eloquent, so the engine stays unit-testable in isolation.
 */
final class AvailabilityService
{
    public function __construct(private readonly SlotGenerator $generator) {}

    /**
     * Start times a customer may pick for a service on a calendar day (in the
     * tenant's timezone). With a staff member: their slots. Without: the union
     * across every active staff member who offers the service, each slot
     * listing who could take it.
     *
     * @return Collection<int, AvailableSlot>
     */
    public function slotsFor(Service $service, CarbonImmutable|string $date, ?Staff $staff = null): Collection
    {
        $timezone = tenant()->timezone;
        $interval = config('booking.slot_interval_minutes');

        $request = new SlotRequest(
            date: $date,
            timezone: $timezone,
            durationMinutes: $service->duration_minutes,
            bufferMinutes: $service->buffer_after_minutes,
            intervalMinutes: $interval === null ? null : (int) $interval,
        );

        /** @var Collection<int, AvailableSlot> $merged */
        $merged = new Collection;

        foreach ($this->staffFor($service, $staff) as $member) {
            $slots = $this->generator->generate($request, $this->calendarFor($member, $request));

            foreach ($slots as $slot) {
                $key = $slot->start->getTimestamp();
                $existing = $merged->get($key);

                $merged->put($key, $existing instanceof AvailableSlot
                    ? $existing->withStaff((int) $member->getKey())
                    : new AvailableSlot($slot, [(int) $member->getKey()]));
            }
        }

        return $merged->sortKeys()->values();
    }

    /**
     * Whether this exact start is still offered for the service by this staff
     * member. Used by the booking action inside its lock, so it must reflect
     * the database as of right now.
     */
    public function isAvailable(Service $service, Staff $staff, CarbonImmutable $start): bool
    {
        $day = $start->setTimezone(tenant()->timezone)->toDateString();

        return $this->slotsFor($service, $day, $staff)
            ->contains(fn (AvailableSlot $slot) => $slot->start()->equalTo($start));
    }

    /**
     * @return EloquentCollection<int, Staff>
     */
    private function staffFor(Service $service, ?Staff $staff): EloquentCollection
    {
        $query = $service->staff()->where('staff.active', true);

        if ($staff !== null) {
            $query->whereKey($staff->getKey());
        }

        return $query->with('schedules')->get();
    }

    private function calendarFor(Staff $staff, SlotRequest $request): StaffCalendar
    {
        // The local day as a UTC window, padded so bookings and absences that start the
        // day before but spill over (including their buffers) are still considered.
        $dayStart = CarbonImmutable::parse($request->day, $request->timezone)->startOfDay()->utc();
        $windowStart = $dayStart->subDay();
        $windowEnd = $dayStart->addDays(2);

        $hours = $staff->schedules->map(fn ($schedule) => $schedule->toWorkingHours())->values()->all();

        $timeOff = TimeOff::query()
            ->where('staff_id', $staff->getKey())
            ->overlapping($windowStart, $windowEnd)
            ->get()
            ->map(fn (TimeOff $absence) => $absence->period())
            ->values()
            ->all();

        $bookings = Booking::query()
            ->where('staff_id', $staff->getKey())
            ->confirmed()
            ->overlapping($windowStart, $windowEnd)
            ->get()
            ->map(fn (Booking $booking) => $booking->blockedPeriod())
            ->values()
            ->all();

        return new StaffCalendar(hours: $hours, timeOff: $timeOff, bookings: $bookings);
    }
}
