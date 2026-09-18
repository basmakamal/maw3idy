<?php

namespace App\Http\Controllers\Api\V1;

use App\Booking\Availability\AvailabilityService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AvailabilityRequest;
use App\Http\Resources\SlotResource;
use App\Models\Service;
use App\Models\Staff;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Knuckles\Scribe\Attributes\UrlParam;

/**
 * Bookable start times for one service on one day.
 *
 * Public, and the most expensive read in the product, so it is throttled per
 * tenant and caller (see ApiServiceProvider).
 */
#[Group('Availability', 'When a service can be booked. Public, and throttled to 60 requests a minute.')]
#[Unauthenticated]
#[UrlParam('tenant', description: 'The business\'s subdomain.', example: 'demo')]
#[UrlParam('service', 'integer', 'The service id.', example: 1)]
final class AvailabilityController extends Controller
{
    /**
     * Start times for a day
     *
     * Returns the start times a customer could actually take: inside the
     * staff's working hours, clear of existing bookings and their buffers,
     * clear of time off, and in the future. Each slot lists the staff who
     * could take it, so "anyone available" is one request, not one per person.
     *
     * A slot can disappear between this call and a booking; creating a booking
     * answers `409` in that case.
     */
    #[Response(content: [
        'data' => [
            ['starts_at' => '2026-10-05T06:00:00+00:00', 'ends_at' => '2026-10-05T06:30:00+00:00', 'staff_ids' => [1, 2]],
            ['starts_at' => '2026-10-05T06:35:00+00:00', 'ends_at' => '2026-10-05T07:05:00+00:00', 'staff_ids' => [1]],
        ],
        'meta' => [
            'date' => '2026-10-05',
            'timezone' => 'Asia/Riyadh',
            'service_id' => 1,
            'staff_id' => null,
            'duration_minutes' => 30,
        ],
    ], status: 200)]
    #[Response(content: [
        'message' => 'The date field is required.',
        'errors' => ['date' => ['The date field is required.']],
    ], status: 422, description: 'A missing, malformed or out-of-window date, or a staff member who does not work here.')]
    public function __invoke(
        AvailabilityRequest $request,
        Service $service,
        AvailabilityService $availability,
    ): AnonymousResourceCollection {
        abort_unless($service->active, 404);

        $staff = $request->has('staff_id')
            ? Staff::query()->active()->findOrFail($request->integer('staff_id'))
            : null;

        $slots = $availability->slotsFor($service, $request->string('date')->toString(), $staff);

        return SlotResource::collection($slots)->additional([
            'meta' => [
                'date' => $request->string('date')->toString(),
                'timezone' => tenant()->timezone,
                'service_id' => $service->id,
                'staff_id' => $staff?->id,
                'duration_minutes' => $service->duration_minutes,
            ],
        ]);
    }
}
