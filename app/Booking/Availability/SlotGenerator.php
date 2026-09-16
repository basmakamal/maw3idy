<?php

namespace App\Booking\Availability;

use App\Enums\Weekday;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The availability engine. Pure: no database, no clock of its own, no tenant
 * lookup. Given a request and a staff member's calendar it returns the start
 * times a customer may pick, as UTC periods.
 *
 * Rules, in the order they are applied to each candidate on the grid:
 *  1. the service itself must fit inside a working block (its buffer may spill past closing);
 *  2. the start must be in the future relative to the injected "now";
 *  3. the service may not overlap time off (again, the buffer may);
 *  4. the service plus its buffer may not overlap an existing booking plus that booking's buffer.
 */
final class SlotGenerator
{
    public function __construct(private readonly CarbonImmutable $now) {}

    /**
     * @return Collection<int, Period>
     */
    public function generate(SlotRequest $request, StaffCalendar $calendar): Collection
    {
        // The weekday of a calendar date does not depend on a timezone.
        $weekday = Weekday::of(CarbonImmutable::parse($request->day, 'UTC'));

        /** @var Collection<int, Period> $slots */
        $slots = new Collection;

        foreach ($calendar->hours as $hours) {
            if ($hours->weekday !== $weekday) {
                continue;
            }

            $block = $hours->on($request->day, $request->timezone);
            if ($block === null) {
                continue;
            }

            for ($start = $block->start; ; $start = $start->addMinutes($request->interval())) {
                $slot = Period::fromMinutes($start, $request->durationMinutes);

                if ($slot->end->greaterThan($block->end)) {
                    break;
                }

                if ($this->isFree($slot, $request->bufferMinutes, $calendar)) {
                    $slots->push($slot);
                }
            }
        }

        return $slots
            ->unique(fn (Period $slot) => $slot->start->getTimestamp())
            ->sortBy(fn (Period $slot) => $slot->start->getTimestamp())
            ->values();
    }

    private function isFree(Period $slot, int $bufferMinutes, StaffCalendar $calendar): bool
    {
        if (! $slot->start->greaterThan($this->now)) {
            return false;
        }

        foreach ($calendar->timeOff as $absence) {
            if ($slot->overlaps($absence)) {
                return false;
            }
        }

        $slotWithBuffer = $slot->extendedBy($bufferMinutes);

        foreach ($calendar->bookings as $booked) {
            if ($slotWithBuffer->overlaps($booked)) {
                return false;
            }
        }

        return true;
    }
}
