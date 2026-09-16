<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * @return array<model-property<Service>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->find()?->getKey() ?? Tenant::factory(),
            'name' => fake()->unique()->randomElement(['Haircut', 'Beard trim', 'Hair colour', 'Blow dry', 'Manicure', 'Pedicure', 'Consultation', 'Massage']),
            'description' => fake()->optional()->sentence(),
            'duration_minutes' => fake()->randomElement([30, 45, 60]),
            'buffer_after_minutes' => 0,
            'price' => fake()->randomElement(['50.00', '80.00', '120.00', '200.00']),
            'active' => true,
        ];
    }

    public function lasting(int $minutes, int $buffer = 0): static
    {
        return $this->state(fn () => ['duration_minutes' => $minutes, 'buffer_after_minutes' => $buffer]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
