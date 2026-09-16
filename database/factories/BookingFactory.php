<?php

namespace Database\Factories;

use App\Booking\BookingReference;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * @return array<model-property<Booking>, mixed>
     */
    public function definition(): array
    {
        return [
            // Parents first: the closures below read the resolved ids.
            'service_id' => Service::factory(),
            'staff_id' => Staff::factory(),
            'tenant_id' => fn (array $attributes) => app(TenantContext::class)->find()?->getKey()
                ?? (is_int($attributes['staff_id'] ?? null) ? Staff::withoutTenancy()->find($attributes['staff_id'])?->tenant_id : null)
                ?? Tenant::factory(),
            'reference' => fn () => BookingReference::generate(),
            'customer_name' => fake()->name(),
            'customer_phone' => '+9665'.fake()->numerify('########'),
            'customer_email' => fake()->optional()->safeEmail(),
            'starts_at' => CarbonImmutable::now()->addWeek()->next('Monday')->setTime(10, 0),
            'duration_minutes' => fn (array $attributes) => $this->service($attributes)->duration_minutes ?? 30,
            'buffer_after_minutes' => fn (array $attributes) => $this->service($attributes)->buffer_after_minutes ?? 0,
            'price' => fn (array $attributes) => $this->service($attributes)->price ?? '0.00',
            'ends_at' => fn (array $attributes) => CarbonImmutable::parse($attributes['starts_at'])->addMinutes((int) $attributes['duration_minutes']),
            'status' => BookingStatus::Confirmed,
            'slot_lock' => true,
            'cancel_token' => fn () => Str::random(40),
        ];
    }

    public function startingAt(CarbonImmutable $start): static
    {
        return $this->state(fn () => ['starts_at' => $start]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Cancelled,
            'slot_lock' => null,
            'cancelled_at' => CarbonImmutable::now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function service(array $attributes): ?Service
    {
        return is_int($attributes['service_id'] ?? null)
            ? Service::withoutTenancy()->find($attributes['service_id'])
            : null;
    }
}
