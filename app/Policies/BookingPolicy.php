<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

/**
 * Anyone who works at the business may read the diary and cancel an
 * appointment: a receptionist taking a phone call needs exactly that. Tenant
 * isolation is the query scope's job, and is re-checked here so a stale id
 * cannot cross the boundary.
 */
final class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Booking $booking): bool
    {
        return (int) $user->tenant_id === (int) $booking->tenant_id;
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $this->view($user, $booking);
    }
}
