<?php

use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('shared inertia data exposes tenant and tenant context for tenant users', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenant.id', $tenant->id)
            ->where('tenant.name', $tenant->name)
            ->where('tenant.slug', $tenant->slug)
            ->where('tenant.logo_url', $tenant->logo_url)
            ->where('tenantContext.canSelect', false)
            ->where('tenantContext.activeTenantId', $tenant->id)
        );
});

test('settings routes remain accessible without tenant middleware', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk();
});
