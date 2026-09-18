<?php

namespace App\Actions\Booking;

use App\Booking\Availability\AvailabilityService;
use App\Events\BookingRescheduled;
use App\Exceptions\Booking\BookingNotChangeableException;
use App\Exceptions\Booking\SlotUnavailableException;
use App\Models\Booking;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Moves a booking to another time with the same service and staff member.
 *
 * Same guard as creating one (ADR-011): lock the staff member, re-check
 * availability with locking reads, then write. The booking's own slot is
 * excluded from that check, or it would block the move to an overlapping time
 * (10:00 to 10:30 for a one-hour service, say).
 */
final class RescheduleBooking
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly CancelBooking $rules,
    ) {}

    /**
     * @throws BookingNotChangeableException|SlotUnavailableException
     */
    public function handle(Booking $booking, CarbonImmutable $newStart, bool $byCustomer = false): Booking
    {
        [$booking, $previousStart] = DB::transaction(function () use ($booking, $newStart, $byCustomer): array {
            /** @var Booking $fresh */
            $fresh = Booking::query()
                ->with(['tenant', 'service', 'staff'])
                ->lockForUpdate()
                ->findOrFail($booking->getKey());

            $this->rules->assertChangeable($fresh, $byCustomer);

            $previousStart = $fresh->starts_at;

            if ($previousStart->equalTo($newStart)) {
                return [$fresh, $previousStart];
            }

            /** @var Staff $staff */
            $staff = Staff::query()->active()->lockForUpdate()->findOrFail($fresh->staff_id);

            $available = $this->availability->isAvailable(
                $fresh->service,
                $staff,
                $newStart,
                forUpdate: true,
                excluding: $fresh,
            );

            if (! $available) {
                throw SlotUnavailableException::at($newStart);
            }

            $fresh->starts_at = $newStart;
            $fresh->ends_at = $newStart->addMinutes($fresh->duration_minutes);

            try {
                $fresh->save();
            } catch (UniqueConstraintViolationException $e) {
                throw SlotUnavailableException::at($newStart, $e);
            }

            return [$fresh, $previousStart];
        });

        if (! $previousStart->equalTo($booking->starts_at)) {
            BookingRescheduled::dispatch($booking, $previousStart);
        }

        return $booking;
    }
}
