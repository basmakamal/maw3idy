<?php

namespace App\Booking\Availability;

use App\Enums\Weekday;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * One block of working time on a weekday, in the tenant's local clock.
 * Several blocks per weekday express split shifts.
 */
final readonly class WorkingHours
{
    private const TIME = '/^(?:[01]\d|2[0-3]):[0-5]\d$/';

    public function __construct(
        public Weekday $weekday,
        public string $start,
        public string $end,
    ) {
        foreach ([$start, $end] as $time) {
            if (preg_match(self::TIME, $time) !== 1) {
                throw new InvalidArgumentException(sprintf('Invalid time "%s"; expected HH:MM.', $time));
            }
        }

        // HH:MM strings compare correctly as strings.
        if ($end <= $start) {
            throw new InvalidArgumentException('Working hours must end after they start.');
        }
    }

    /**
     * Resolve to a concrete UTC period on a calendar day ("Y-m-d") in a timezone.
     * Returns null when a DST jump swallows the whole block.
     */
    public function on(string $day, string $timezone): ?Period
    {
        $start = CarbonImmutable::parse("{$day} {$this->start}", $timezone);
        $end = CarbonImmutable::parse("{$day} {$this->end}", $timezone);

        return $end->greaterThan($start) ? new Period($start, $end) : null;
    }
}
