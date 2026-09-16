<?php

namespace Database\Factories;

use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TimeOff;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeOff>
 */
class TimeOffFactory extends Factory
{
    /**
     * @return array<model-property<TimeOff>, mixed>
     */
    public function definition(): array
    {
        $start = CarbonImmutable::now()->addWeek()->startOfDay();

        return [
            'staff_id' => Staff::factory(),
            'tenant_id' => fn (array $attributes) => app(TenantContext::class)->find()?->getKey()
                ?? (is_int($attributes['staff_id'] ?? null) ? Staff::withoutTenancy()->find($attributes['staff_id'])?->tenant_id : null)
                ?? Tenant::factory(),
            'starts_at' => $start,
            'ends_at' => $start->addDay(),
            'reason' => fake()->optional()->randomElement(['Holiday', 'Sick', 'Training']),
        ];
    }

    public function between(CarbonImmutable $start, CarbonImmutable $end): static
    {
        return $this->state(fn () => ['starts_at' => $start, 'ends_at' => $end]);
    }
}
