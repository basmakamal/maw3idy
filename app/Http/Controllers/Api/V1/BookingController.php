<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Booking\CancelBooking;
use App\Actions\Booking\CreateBooking;
use App\Enums\TokenAbility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Bookings over the API. Reads and writes need separate token abilities, so an
 * integration that only displays the diary cannot take a slot.
 */
final class BookingController extends Controller
{
    /**
     * @return AnonymousResourceCollection<int, BookingResource>
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->requireAbility($request, TokenAbility::ReadBookings);

        $validated = $request->validate([
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'staff_id' => ['sometimes', 'integer'],
            'status' => ['sometimes', 'string', 'in:confirmed,cancelled'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $bookings = Booking::query()
            ->with(['service', 'staff'])
            ->when(isset($validated['from']), fn ($query) => $query->where('starts_at', '>=', CarbonImmutable::parse($validated['from'])->utc()))
            ->when(isset($validated['to']), fn ($query) => $query->where('starts_at', '<=', CarbonImmutable::parse($validated['to'])->utc()))
            ->when(isset($validated['staff_id']), fn ($query) => $query->where('staff_id', $validated['staff_id']))
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->orderBy('starts_at')
            ->paginate($validated['per_page'] ?? 25)
            ->withQueryString();

        return BookingResource::collection($bookings)
            ->additional(['meta' => ['timezone' => tenant()->timezone]]);
    }

    public function store(StoreBookingRequest $request, CreateBooking $createBooking): JsonResponse
    {
        $this->requireAbility($request, TokenAbility::WriteBookings);

        $booking = $createBooking->handle($request->toData());

        return (new BookingResource($booking->load(['service', 'staff'])))
            ->additional(['meta' => ['timezone' => tenant()->timezone]])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Booking $booking): BookingResource
    {
        $this->requireAbility($request, TokenAbility::ReadBookings);

        return (new BookingResource($booking->load(['service', 'staff'])))
            ->additional(['meta' => ['timezone' => tenant()->timezone]]);
    }

    public function destroy(Request $request, Booking $booking, CancelBooking $cancelBooking): BookingResource
    {
        $this->requireAbility($request, TokenAbility::WriteBookings);

        $validated = $request->validate([
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $cancelBooking->handle($booking, $validated['reason'] ?? null);

        return new BookingResource($booking->refresh()->load(['service', 'staff']));
    }

    private function requireAbility(Request $request, TokenAbility $ability): void
    {
        abort_unless($request->user()?->tokenCan($ability->value) ?? false, 403, __('This token is not allowed to do that.'));
    }
}
