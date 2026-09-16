<?php

namespace App\Booking\Availability;

use Carbon\CarbonImmutable;

/**
 * A bookable start time and the staff members who could take it.
 */
final readonly class AvailableSlot
{
    /**
     * @param  list<int>  $staffIds
     */
    public function __construct(
        public Period $period,
        public array $staffIds,
    ) {}

    public function start(): CarbonImmutable
    {
        return $this->period->start;
    }

    public function withStaff(int $staffId): self
    {
        return new self($this->period, array_values(array_unique([...$this->staffIds, $staffId])));
    }

    public function offeredBy(int $staffId): bool
    {
        return in_array($staffId, $this->staffIds, true);
    }
}
