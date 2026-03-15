<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProcessingEvent>
 */
class ProcessingEventFactory extends Factory
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
            'message_id' => (string) fake()->uuid(),
            'trace_id' => (string) fake()->uuid(),
            'event' => 'document.status.transitioned',
            'consumer_name' => fake()->randomElement(['upload-dispatch', 'virus-scan', 'ocr-extraction']),
            'status_from' => DocumentStatus::Uploaded->value,
            'status_to' => fake()->randomElement([
                DocumentStatus::Scanning->value,
                DocumentStatus::ReadyForReview->value,
            ]),
            'metadata' => [
                'attempt' => fake()->numberBetween(1, 3),
            ],
        ];
    }
}
