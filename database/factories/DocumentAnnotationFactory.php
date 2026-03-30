<?php

namespace Database\Factories;

use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentAnnotation>
 */
class DocumentAnnotationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attributes): ?string => Document::query()->find($attributes['document_id'])?->tenant_id,
            'document_id' => Document::factory(),
            'user_id' => null,
            'type' => fake()->randomElement(['highlight', 'comment', 'note']),
            'page_number' => fake()->numberBetween(1, 4),
            'coordinates' => [
                'x' => fake()->randomFloat(4, 0.05, 0.6),
                'y' => fake()->randomFloat(4, 0.05, 0.6),
                'width' => fake()->randomFloat(4, 0.1, 0.25),
                'height' => fake()->randomFloat(4, 0.04, 0.16),
            ],
            'content' => fake()->optional()->sentence(),
        ];
    }
}
