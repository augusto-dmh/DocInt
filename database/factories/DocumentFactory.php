<?php

namespace Database\Factories;

use App\Models\Matter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Document>
 */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attributes): ?string => Matter::query()->find($attributes['matter_id'])?->tenant_id,
            'matter_id' => Matter::factory(),
            'uploaded_by' => null,
            'title' => fake()->sentence(3),
            'file_path' => fake()->filePath(),
            'file_name' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(10000, 5000000),
            'status' => 'uploaded',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'approved',
        ]);
    }

    public function readyForReview(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'ready_for_review',
        ]);
    }
}
