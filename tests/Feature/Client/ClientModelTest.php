<?php

use App\Models\Client;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\HasMany;

afterEach(function (): void {
    tenancy()->end();
});

test('client factory creates a valid client', function (): void {
    $tenant = Tenant::factory()->create();

    $client = Client::factory()->create(['tenant_id' => $tenant->id]);

    expect($client->tenant_id)->toBe($tenant->id)
        ->and($client->name)->not->toBeEmpty();
});

test('client belongs to a tenant', function (): void {
    $tenant = Tenant::factory()->create();

    $client = Client::factory()->create(['tenant_id' => $tenant->id]);

    expect($client->tenant->id)->toBe($tenant->id);
});

test('client has many matters relationship', function (): void {
    $tenant = Tenant::factory()->create();

    $client = Client::factory()->create(['tenant_id' => $tenant->id]);

    expect($client->matters())->toBeInstanceOf(HasMany::class);
});

test('client query is scoped to current tenant', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    Client::factory()->count(2)->create(['tenant_id' => $tenantA->id]);
    Client::factory()->count(3)->create(['tenant_id' => $tenantB->id]);

    tenancy()->initialize($tenantA);
    expect(Client::query()->count())->toBe(2);

    tenancy()->initialize($tenantB);
    expect(Client::query()->count())->toBe(3);
});
