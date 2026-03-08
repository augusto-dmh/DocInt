<?php

namespace Database\Factories;

use App\Enums\MatterStatus;
use App\Models\Client;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Matter>
 */
class MatterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attributes): string => Client::query()->find($attributes['client_id'])?->tenant_id ?? Tenant::factory()->create()->id,
            'client_id' => Client::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'reference_number' => fake()->optional()->bothify('MAT-####'),
            'status' => MatterStatus::Open,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => MatterStatus::Closed,
        ]);
    }

    public function onHold(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => MatterStatus::OnHold,
        ]);
    }
}
