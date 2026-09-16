<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Contracts\View\View;

/**
 * The customer's own view of their booking, reached from the link in their
 * email. The route is signed (so a crafted or truncated link is refused before
 * any lookup) and the token is the capability that identifies the booking;
 * see ADR-016.
 */
final class ManageBookingController extends Controller
{
    public function __invoke(string $token): View
    {
        $booking = Booking::query()->where('cancel_token', $token)->first();

        if ($booking === null) {
            abort(404);
        }

        return view('booking.manage', ['token' => $token]);
    }
}
