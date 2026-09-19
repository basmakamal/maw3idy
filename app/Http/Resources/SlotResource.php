<?php

namespace App\Http\Resources;

use App\Booking\Availability\AvailableSlot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AvailableSlot
 */
class SlotResource extends JsonResource
{
    /**
     * Instants are UTC and explicitly offset-marked; the tenant's timezone
     * travels in the response meta so a client can render local times without
     * guessing.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'starts_at' => $this->period->start->toIso8601String(),
            'ends_at' => $this->period->end->toIso8601String(),
            'staff_ids' => $this->staffIds,
        ];
    }
}
