<?php

namespace App\Actions\Booking;

use App\Booking\Availability\AvailabilityService;
use App\Booking\Availability\AvailableSlot;
use App\Booking\BookingReference;
use App\Data\BookingRequestData;
use App\Enums\BookingStatus;
use App\Events\BookingCreated;
use App\Exceptions\Booking\SlotUnavailableException;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Staff;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Books a slot without ever double-booking a staff member.
 *
 * Two customers can pick the same slot on their screens at the same time; only
 * one may get it. Inside one transaction we (1) lock the staff member's row,
 * which serialises concurrent attempts for that person, (2) re-check
 * availability against the database as it is *now*, and (3) insert. The
 * unique index on (staff_id, starts_at, slot_lock) is the backstop should
 * anything bypass this path. See ADR-011.
 */
final class CreateBooking
{
    public function __construct(private readonly AvailabilityService $availability) {}

    /**
     * @throws SlotUnavailableException
     */
    public function handle(BookingRequestData $data): Booking
    {
        $service = Service::query()->active()->findOrFail($data->serviceId);

        $booking = DB::transaction(function () use ($service, $data): Booking {
            $staff = $this->lockAvailableStaff($service, $data);

            $booking = new Booking([
                'customer_name' => $data->customerName,
                'customer_phone' => $data->customerPhone,
                'customer_email' => $data->customerEmail,
            ]);

            $booking->service()->associate($service);
            $booking->staff()->associate($staff);
            $booking->starts_at = $data->start;
            $booking->ends_at = $data->start->addMinutes($service->duration_minutes);
            $booking->duration_minutes = $service->duration_minutes;
            $booking->buffer_after_minutes = $service->buffer_after_minutes;
            $booking->price = $service->price;
            $booking->status = BookingStatus::Confirmed;
            $booking->slot_lock = true;
            $booking->reference = $this->uniqueReference();
            $booking->cancel_token = Str::random(40);

            try {
                $booking->save();
            } catch (UniqueConstraintViolationException $e) {
                throw SlotUnavailableException::at($data->start, $e);
            }

            return $booking;
        });

        BookingCreated::dispatch($booking);

        return $booking;
    }

    /**
     * Lock the staff member who will take the booking and prove, under that
     * lock, that the slot is still free. For "any staff" the candidates are
     * locked in id order so two concurrent "any" requests cannot deadlock.
     *
     * @throws SlotUnavailableException
     */
    private function lockAvailableStaff(Service $service, BookingRequestData $data): Staff
    {
        $candidateIds = $data->staffId !== null
            ? [$data->staffId]
            : $this->candidateIds($service, $data);

        sort($candidateIds);

        foreach ($candidateIds as $id) {
            $staff = Staff::query()
                ->active()
                ->whereKey($id)
                ->whereHas('services', fn ($query) => $query->whereKey($service->getKey()))
                ->lockForUpdate()
                ->first();

            if ($staff !== null && $this->availability->isAvailable($service, $staff, $data->start, forUpdate: true)) {
                return $staff;
            }
        }

        throw $data->staffId !== null
            ? SlotUnavailableException::at($data->start)
            : SlotUnavailableException::noStaff($data->start);
    }

    /**
     * Who could take this slot, judged before locking. Each is re-verified under lock.
     *
     * @return list<int>
     */
    private function candidateIds(Service $service, BookingRequestData $data): array
    {
        $day = $data->start->setTimezone(tenant()->timezone)->toDateString();

        $slot = $this->availability->slotsFor($service, $day)
            ->first(fn (AvailableSlot $slot) => $slot->start()->equalTo($data->start));

        return $slot->staffIds ?? [];
    }

    private function uniqueReference(): string
    {
        do {
            $reference = BookingReference::generate();
        } while (Booking::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
