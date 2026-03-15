<?php

namespace Database\Factories;

use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExtractedData>
 */
class ExtractedDataFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attributes): string => Document::query()->find($attributes['document_id'])?->tenant_id
                ?? Document::factory()->create()->tenant_id,
            'document_id' => Document::factory(),
            'provider' => 'simulated',
            'extracted_text' => fake()->paragraph(),
            'payload' => [
                'lines' => [fake()->sentence(), fake()->sentence()],
            ],
            'metadata' => [
                'language' => 'en',
            ],
        ];
    }
}
