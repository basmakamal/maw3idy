<?php

namespace App\Booking\Availability;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * A half-open interval of time [start, end) in UTC.
 *
 * Half-open means two periods that merely touch (one ends exactly when the
 * next starts) do not overlap, which is what back-to-back bookings need.
 */
final readonly class Period
{
    public CarbonImmutable $start;

    public CarbonImmutable $end;

    public function __construct(CarbonImmutable $start, CarbonImmutable $end)
    {
        if (! $end->greaterThan($start)) {
            throw new InvalidArgumentException('A period must end after it starts.');
        }

        $this->start = $start->utc();
        $this->end = $end->utc();
    }

    public static function fromMinutes(CarbonImmutable $start, int $minutes): self
    {
        return new self($start, $start->addMinutes($minutes));
    }

    public function overlaps(self $other): bool
    {
        return $this->start->lessThan($other->end) && $other->start->lessThan($this->end);
    }

    public function encloses(self $other): bool
    {
        return ! $other->start->lessThan($this->start) && ! $other->end->greaterThan($this->end);
    }

    /**
     * The same start, ending $minutes later. Used to append a service's buffer.
     */
    public function extendedBy(int $minutes): self
    {
        return $minutes > 0 ? new self($this->start, $this->end->addMinutes($minutes)) : $this;
    }

    public function minutes(): int
    {
        return (int) $this->start->diffInMinutes($this->end);
    }

    public function startsWith(self $other): bool
    {
        return $this->start->equalTo($other->start);
    }
}
