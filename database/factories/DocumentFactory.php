<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\Matter;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Document>
 */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attributes): string => Matter::query()->find($attributes['matter_id'])?->tenant_id ?? Tenant::factory()->create()->id,
            'matter_id' => Matter::factory(),
            'uploaded_by' => null,
            'title' => fake()->sentence(3),
            'file_path' => fake()->filePath(),
            'file_name' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(10000, 5000000),
            'status' => DocumentStatus::Uploaded,
        ];
    }

    public function approved(): static
    {
        return $this->forStatus(DocumentStatus::Approved);
    }

    public function readyForReview(): static
    {
        return $this->forStatus(DocumentStatus::ReadyForReview);
    }

    public function scanning(): static
    {
        return $this->forStatus(DocumentStatus::Scanning);
    }

    public function scanPassed(): static
    {
        return $this->forStatus(DocumentStatus::ScanPassed);
    }

    public function extracting(): static
    {
        return $this->forStatus(DocumentStatus::Extracting);
    }

    public function classifying(): static
    {
        return $this->forStatus(DocumentStatus::Classifying);
    }

    public function reviewed(): static
    {
        return $this->forStatus(DocumentStatus::Reviewed);
    }

    public function rejected(): static
    {
        return $this->forStatus(DocumentStatus::Rejected);
    }

    public function scanFailed(): static
    {
        return $this->forStatus(DocumentStatus::ScanFailed);
    }

    public function extractionFailed(): static
    {
        return $this->forStatus(DocumentStatus::ExtractionFailed);
    }

    public function classificationFailed(): static
    {
        return $this->forStatus(DocumentStatus::ClassificationFailed);
    }

    protected function forStatus(DocumentStatus $status): static
    {
        return $this->state(fn (): array => [
            'status' => $status,
        ]);
    }
}
