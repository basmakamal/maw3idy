<?php

namespace Database\Factories;

use App\Enums\Weekday;
use App\Models\Schedule;
use App\Models\Staff;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    /**
     * @return array<model-property<Schedule>, mixed>
     */
    public function definition(): array
    {
        return [
            // staff_id first: the tenant closure below reads the resolved staff member.
            'staff_id' => Staff::factory(),
            'tenant_id' => fn (array $attributes) => app(TenantContext::class)->find()?->getKey()
                ?? (is_int($attributes['staff_id'] ?? null) ? Staff::withoutTenancy()->find($attributes['staff_id'])?->tenant_id : null)
                ?? Tenant::factory(),
            'weekday' => Weekday::Monday,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ];
    }

    public function on(Weekday $weekday, string $start = '09:00', string $end = '17:00'): static
    {
        return $this->state(fn () => ['weekday' => $weekday, 'start_time' => $start, 'end_time' => $end]);
    }
}
