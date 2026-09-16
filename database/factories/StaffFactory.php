<?php

namespace Database\Factories;

use App\Models\Staff;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Staff>
 */
class StaffFactory extends Factory
{
    /**
     * @return array<model-property<Staff>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->find()?->getKey() ?? Tenant::factory(),
            'name' => fake()->firstName(),
            'email' => fake()->optional(0.7)->safeEmail(),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
