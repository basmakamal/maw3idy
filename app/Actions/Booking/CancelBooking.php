<?php

namespace App\Actions\Booking;

use App\Events\BookingCancelled;
use App\Exceptions\Booking\BookingNotChangeableException;
use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Releases a booking's slot.
 *
 * Clearing slot_lock is what lets the unique index accept a new booking at the
 * same time; the row is kept so the business can see what happened.
 */
final class CancelBooking
{
    /**
     * @param  bool  $byCustomer  customers are held to the notice period; staff are not
     *
     * @throws BookingNotChangeableException
     */
    public function handle(Booking $booking, ?string $reason = null, bool $byCustomer = false): Booking
    {
        $booking = DB::transaction(function () use ($booking, $reason, $byCustomer): Booking {
            // Re-read under a lock: two tabs, or a customer and the salon at once.
            /** @var Booking $fresh */
            $fresh = Booking::query()
                ->with(['tenant', 'service', 'staff'])
                ->lockForUpdate()
                ->findOrFail($booking->getKey());

            $this->assertChangeable($fresh, $byCustomer);

            $fresh->cancel($reason);

            return $fresh;
        });

        BookingCancelled::dispatch($booking, $byCustomer);

        return $booking;
    }

    /**
     * @throws BookingNotChangeableException
     */
    public function assertChangeable(Booking $booking, bool $byCustomer): void
    {
        if (! $booking->isConfirmed()) {
            throw BookingNotChangeableException::alreadyCancelled();
        }

        $now = CarbonImmutable::now();

        if (! $booking->starts_at->greaterThan($now)) {
            throw BookingNotChangeableException::alreadyPast();
        }

        if (! $byCustomer) {
            return;
        }

        $noticeHours = (int) config('booking.cancellation_notice_hours');

        if ($booking->starts_at->lessThan($now->addHours($noticeHours))) {
            throw BookingNotChangeableException::tooLate($noticeHours);
        }
    }
}
