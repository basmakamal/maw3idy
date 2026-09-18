<?php

namespace App\Http\Controllers\Api\V1;

use App\Booking\Availability\AvailabilityService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AvailabilityRequest;
use App\Http\Resources\SlotResource;
use App\Models\Service;
use App\Models\Staff;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Bookable start times for one service on one day.
 *
 * Public, and the most expensive read in the product, so it is throttled per
 * tenant and caller (see ApiServiceProvider).
 */
final class AvailabilityController extends Controller
{
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
