<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Knuckles\Scribe\Attributes\UrlParam;

/**
 * The catalogue a booking widget needs. Public and read-only: it is the same
 * information the tenant's own booking page already shows anyone.
 */
#[Group('Services', 'What the business offers. Public, and throttled to 60 requests a minute.')]
#[Unauthenticated]
#[UrlParam('tenant', description: 'The business\'s subdomain.', example: 'demo')]
final class ServiceController extends Controller
{
    /**
     * List bookable services
     *
     * Only services that are active and have at least one active staff member
     * are listed: anything else could not be booked.
     *
     * @return AnonymousResourceCollection<int, ServiceResource>
     */
    #[Response(content: [
        'data' => [
            [
                'id' => 1,
                'name' => 'Haircut',
                'description' => 'Wash, cut and style.',
                'duration_minutes' => 30,
                'buffer_after_minutes' => 5,
                'price' => '80.00',
                'currency' => 'SAR',
                'active' => true,
                'staff' => [['id' => 1, 'name' => 'Sara', 'active' => true]],
            ],
        ],
        'meta' => ['timezone' => 'Asia/Riyadh'],
    ], status: 200)]
    public function index(): AnonymousResourceCollection
    {
        $services = Service::query()
            ->active()
            ->has('staff')
            ->with(['staff' => fn ($query) => $query->active()->orderBy('name')])
            ->orderBy('name')
            ->get();

        return ServiceResource::collection($services)
            ->additional(['meta' => ['timezone' => tenant()->timezone]]);
    }

    /**
     * Show one service
     */
    #[UrlParam('service', 'integer', 'The service id.', example: 1)]
    #[Response(content: [
        'data' => [
            'id' => 1,
            'name' => 'Haircut',
            'description' => 'Wash, cut and style.',
            'duration_minutes' => 30,
            'buffer_after_minutes' => 5,
            'price' => '80.00',
            'currency' => 'SAR',
            'active' => true,
            'staff' => [['id' => 1, 'name' => 'Sara', 'active' => true]],
        ],
        'meta' => ['timezone' => 'Asia/Riyadh'],
    ], status: 200)]
    #[Response(content: ['message' => 'Not Found'], status: 404, description: 'No such service, or it is not bookable.')]
    public function show(Service $service): ServiceResource
    {
        abort_unless($service->active, 404);

        return (new ServiceResource($service->load(['staff' => fn ($query) => $query->active()->orderBy('name')])))
            ->additional(['meta' => ['timezone' => tenant()->timezone]]);
    }
}
