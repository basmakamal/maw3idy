<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Shows the booking that was just made. The booking id lives in the
 * customer's session, so a reference or id in the URL can never expose
 * another customer's name and phone number.
 */
final class BookingConfirmationController extends Controller
{
    public function __invoke(Request $request): View
    {
        $id = $request->session()->get('confirmed_booking');

        $booking = is_numeric($id)
            ? Booking::query()->with(['service', 'staff'])->find((int) $id)
            : null;

        if ($booking === null) {
            abort(404);
        }

        return view('booking.confirmed', [
            'booking' => $booking,
            'timezone' => tenant()->timezone,
        ]);
    }
}
