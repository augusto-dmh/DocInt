<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentComment>
 */
class DocumentCommentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attributes): string => Document::query()->find($attributes['document_id'])?->tenant_id
                ?? Tenant::factory()->create()->id,
            'document_id' => Document::factory(),
            'user_id' => User::factory(),
            'parent_id' => null,
            'body' => fake()->paragraph(),
        ];
    }

    public function replyTo(int $parentId): static
    {
        return $this->state(fn (): array => [
            'parent_id' => $parentId,
        ]);
    }
}
