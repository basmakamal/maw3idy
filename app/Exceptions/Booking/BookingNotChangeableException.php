<?php

namespace App\Exceptions\Booking;

use DomainException;

/**
 * The booking cannot be cancelled or moved: it is already cancelled, already
 * past, or too close to its start for the customer to change it themselves.
 */
final class BookingNotChangeableException extends DomainException
{
    public static function alreadyCancelled(): self
    {
        return new self('This booking has already been cancelled.');
    }

    public static function alreadyPast(): self
    {
        return new self('This booking is in the past.');
    }

    public static function tooLate(int $hours): self
    {
        return new self(sprintf('Bookings can only be changed more than %d hour(s) in advance.', $hours));
    }
}
