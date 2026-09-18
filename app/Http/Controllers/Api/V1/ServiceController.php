<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The catalogue a booking widget needs. Public and read-only: it is the same
 * information the tenant's own booking page already shows anyone.
 */
final class ServiceController extends Controller
{
    /**
     * @return AnonymousResourceCollection<int, ServiceResource>
     */
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

    public function show(Service $service): ServiceResource
    {
        abort_unless($service->active, 404);

        return (new ServiceResource($service->load(['staff' => fn ($query) => $query->active()->orderBy('name')])))
            ->additional(['meta' => ['timezone' => tenant()->timezone]]);
    }
}
