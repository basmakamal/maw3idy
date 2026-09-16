<?php

namespace App\Exceptions\Booking;

use Carbon\CarbonImmutable;
use DomainException;
use Throwable;

/**
 * The requested start is not (or no longer) bookable: outside working hours,
 * taken by another customer between selection and confirmation, or the staff
 * member cannot take it. Callers show a friendly message and re-offer slots.
 */
final class SlotUnavailableException extends DomainException
{
    public static function at(CarbonImmutable $start, ?Throwable $previous = null): self
    {
        return new self(sprintf('The slot starting at %s is no longer available.', $start->toIso8601String()), 0, $previous);
    }

    public static function noStaff(CarbonImmutable $start): self
    {
        return new self(sprintf('No staff member can take the slot starting at %s.', $start->toIso8601String()));
    }
}
