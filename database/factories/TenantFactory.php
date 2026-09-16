<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * @return array<model-property<Tenant>, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => Str::slug(fake()->unique()->words(2, true)).'-'.fake()->unique()->numberBetween(100, 999),
            'timezone' => 'Asia/Riyadh',
            'locale' => 'en',
            'settings' => [],
        ];
    }

    public function arabic(): static
    {
        return $this->state(fn (array $attributes) => ['locale' => 'ar']);
    }
}
