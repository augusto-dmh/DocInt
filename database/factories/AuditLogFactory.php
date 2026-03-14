<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $document = Document::factory();

        return [
            'tenant_id' => fn (array $attributes): string => Document::query()->find($attributes['auditable_id'])?->tenant_id
                ?? Tenant::factory()->create()->id,
            'user_id' => null,
            'auditable_type' => Document::class,
            'auditable_id' => $document,
            'action' => fake()->randomElement(['uploaded', 'viewed', 'downloaded', 'deleted']),
            'metadata' => null,
        ];
    }
}
