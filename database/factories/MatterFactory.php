<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Matter>
 */
class MatterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attributes): ?string => Client::query()->find($attributes['client_id'])?->tenant_id,
            'client_id' => Client::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'reference_number' => fake()->optional()->bothify('MAT-####'),
            'status' => 'open',
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'closed',
        ]);
    }

    public function onHold(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'on_hold',
        ]);
    }
}
