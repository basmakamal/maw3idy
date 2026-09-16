<?php

namespace App\Booking\Availability;

use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * What the customer wants: a service of a given length on a given calendar day.
 *
 * The day is a plain "Y-m-d" interpreted in the tenant's timezone, so the
 * caller never has to think about which side of midnight UTC it falls on.
 */
final readonly class SlotRequest
{
    public string $day;

    public function __construct(
        CarbonInterface|string $date,
        public string $timezone,
        public int $durationMinutes,
        public int $bufferMinutes = 0,
        public ?int $intervalMinutes = null,
    ) {
        $day = $date instanceof CarbonInterface ? $date->toDateString() : $date;

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid day "%s"; expected Y-m-d.', $day));
        }
        if ($durationMinutes < 1) {
            throw new InvalidArgumentException('Duration must be at least one minute.');
        }
        if ($bufferMinutes < 0) {
            throw new InvalidArgumentException('Buffer cannot be negative.');
        }
        if ($intervalMinutes !== null && $intervalMinutes < 1) {
            throw new InvalidArgumentException('Interval must be at least one minute.');
        }

        $this->day = $day;
    }

    /**
     * Minutes between offered start times: explicit, or back-to-back by default.
     */
    public function interval(): int
    {
        return $this->intervalMinutes ?? $this->durationMinutes;
    }
}
