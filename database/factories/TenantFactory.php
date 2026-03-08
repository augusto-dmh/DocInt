<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();
        $slug = Str::slug($name).'-'.fake()->unique()->numberBetween(1, 9999);

        return [
            'id' => $slug,
            'name' => $name,
            'slug' => $slug,
            'logo_url' => null,
        ];
    }
}
