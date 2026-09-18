<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Booking\CancelBooking;
use App\Actions\Booking\CreateBooking;
use App\Enums\TokenAbility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CancelBookingRequest;
use App\Http\Requests\Api\V1\ListBookingsRequest;
use App\Http\Requests\Api\V1\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\UrlParam;

/**
 * Bookings over the API. Reads and writes need separate token abilities, so an
 * integration that only displays the diary cannot take a slot.
 */
#[Group('Bookings', 'Reading the diary and changing it. Needs a token with the matching ability.')]
#[Authenticated]
#[UrlParam('tenant', description: 'The business\'s subdomain.', example: 'demo')]
final class BookingController extends Controller
{
    /**
     * List bookings
     *
     * Needs the `bookings:read` ability. Paginated, oldest appointment first.
     *
     * @return AnonymousResourceCollection<int, BookingResource>
     */
    #[Response(content: [
        'data' => [
            [
                'reference' => 'MW-7K3P9Q',
                'status' => 'confirmed',
                'starts_at' => '2026-10-05T07:00:00+00:00',
                'ends_at' => '2026-10-05T07:30:00+00:00',
                'duration_minutes' => 30,
                'buffer_after_minutes' => 5,
                'price' => '80.00',
                'currency' => 'SAR',
                'customer' => ['name' => 'Layla A.', 'phone' => '+966501112222', 'email' => null],
                'cancelled_at' => null,
                'cancellation_reason' => null,
                'created_at' => '2026-09-28T09:14:00+00:00',
            ],
        ],
        'meta' => ['timezone' => 'Asia/Riyadh', 'current_page' => 1, 'per_page' => 25, 'total' => 1],
    ], status: 200)]
    #[Response(content: ['message' => 'This token is not allowed to do that.'], status: 403)]
    public function index(ListBookingsRequest $request): AnonymousResourceCollection
    {
        $this->requireAbility($request, TokenAbility::ReadBookings);

        $bookings = Booking::query()
            ->with(['service', 'staff'])
            ->when($request->startingFrom(), fn ($query, $from) => $query->where('starts_at', '>=', $from))
            ->when($request->startingUntil(), fn ($query, $to) => $query->where('starts_at', '<=', $to))
            ->when($request->staffId(), fn ($query, $staffId) => $query->where('staff_id', $staffId))
            ->when($request->status(), fn ($query, $status) => $query->where('status', $status))
            ->orderBy('starts_at')
            ->paginate($request->perPage())
            ->withQueryString();

        return BookingResource::collection($bookings)
            ->additional(['meta' => ['timezone' => tenant()->timezone]]);
    }

    /**
     * Create a booking
     *
     * Needs the `bookings:write` ability.
     *
     * Omit `staff_id` to let the platform pick anyone who offers the service
     * and is free. The slot is verified again under a lock before the booking
     * is written, so two callers racing for the same time cannot both win: the
     * loser gets `409`.
     *
     * The service's duration, buffer and price are copied onto the booking, so
     * later changes to the service never rewrite an existing appointment.
     */
    #[Response(content: [
        'data' => [
            'reference' => 'MW-7K3P9Q',
            'status' => 'confirmed',
            'starts_at' => '2026-10-05T07:00:00+00:00',
            'ends_at' => '2026-10-05T07:30:00+00:00',
            'duration_minutes' => 30,
            'buffer_after_minutes' => 5,
            'price' => '80.00',
            'currency' => 'SAR',
            'customer' => ['name' => 'Layla A.', 'phone' => '+966501112222', 'email' => null],
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'created_at' => '2026-09-28T09:14:00+00:00',
        ],
        'meta' => ['timezone' => 'Asia/Riyadh'],
    ], status: 201)]
    #[Response(content: ['message' => 'The slot starting at 2026-10-05T07:00:00+00:00 is no longer available.'], status: 409, description: 'The time was taken while the caller was deciding.')]
    #[Response(content: [
        'message' => 'The customer name field is required.',
        'errors' => ['customer_name' => ['The customer name field is required.']],
    ], status: 422)]
    public function store(StoreBookingRequest $request, CreateBooking $createBooking): JsonResponse
    {
        $this->requireAbility($request, TokenAbility::WriteBookings);

        $booking = $createBooking->handle($request->toData());

        return (new BookingResource($booking->load(['service', 'staff'])))
            ->additional(['meta' => ['timezone' => tenant()->timezone]])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show one booking
     *
     * Needs the `bookings:read` ability.
     */
    #[UrlParam('reference', 'string', 'The booking reference, as returned when it was created.', example: 'MW-7K3P9Q')]
    #[Response(content: ['message' => 'Not Found'], status: 404, description: 'No such booking at this business.')]
    public function show(Request $request, Booking $booking): BookingResource
    {
        $this->requireAbility($request, TokenAbility::ReadBookings);

        return (new BookingResource($booking->load(['service', 'staff'])))
            ->additional(['meta' => ['timezone' => tenant()->timezone]]);
    }

    /**
     * Cancel a booking
     *
     * Needs the `bookings:write` ability. The appointment's time becomes
     * immediately bookable again and the customer is notified. Staff are not
     * held to the customer notice period.
     */
    #[UrlParam('reference', 'string', 'The booking reference, as returned when it was created.', example: 'MW-7K3P9Q')]
    #[Response(content: ['message' => 'This booking has already been cancelled.'], status: 422, description: 'Already cancelled, or in the past.')]
    public function destroy(CancelBookingRequest $request, Booking $booking, CancelBooking $cancelBooking): BookingResource
    {
        $this->requireAbility($request, TokenAbility::WriteBookings);

        $cancelBooking->handle($booking, $request->reason());

        return new BookingResource($booking->refresh()->load(['service', 'staff']));
    }

    private function requireAbility(Request $request, TokenAbility $ability): void
    {
        abort_unless($request->user()?->tokenCan($ability->value) ?? false, 403, __('This token is not allowed to do that.'));
    }
}
