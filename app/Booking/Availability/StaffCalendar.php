<?php

namespace App\Booking\Availability;

/**
 * Everything the engine needs to know about one staff member: their weekly
 * hours, their absences and the bookings they already hold. Plain data; the
 * caller (usually AvailabilityService) is responsible for loading it.
 */
final readonly class StaffCalendar
{
    /**
     * @param  list<WorkingHours>  $hours  weekly hours, any weekday; the engine picks the relevant ones
     * @param  list<Period>  $timeOff  absences as UTC periods
     * @param  list<Period>  $bookings  existing bookings as UTC periods, each already extended by its own buffer
     */
    public function __construct(
        public array $hours = [],
        public array $timeOff = [],
        public array $bookings = [],
    ) {}
}
