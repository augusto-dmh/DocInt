<?php

use App\Models\Client;
use App\Models\Matter;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\HasMany;

afterEach(function (): void {
    tenancy()->end();
});

test('matter factory creates a valid matter', function (): void {
    $tenant = Tenant::factory()->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);

    $matter = Matter::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

    expect($matter->tenant_id)->toBe($tenant->id)
        ->and($matter->client_id)->toBe($client->id)
        ->and($matter->status)->toBe('open');
});

test('matter belongs to a client', function (): void {
    $tenant = Tenant::factory()->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);

    $matter = Matter::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

    expect($matter->client->id)->toBe($client->id);
});

test('matter has many documents relationship', function (): void {
    $tenant = Tenant::factory()->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = Matter::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

    expect($matter->documents())->toBeInstanceOf(HasMany::class);
});

test('matter closed state sets status to closed', function (): void {
    $tenant = Tenant::factory()->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);

    $matter = Matter::factory()->closed()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

    expect($matter->status)->toBe('closed');
});

test('matter on hold state sets status to on hold', function (): void {
    $tenant = Tenant::factory()->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);

    $matter = Matter::factory()->onHold()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

    expect($matter->status)->toBe('on_hold');
});

test('matter query is scoped to current tenant', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $clientA = Client::factory()->create(['tenant_id' => $tenantA->id]);
    $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);

    Matter::factory()->count(2)->create(['tenant_id' => $tenantA->id, 'client_id' => $clientA->id]);
    Matter::factory()->count(4)->create(['tenant_id' => $tenantB->id, 'client_id' => $clientB->id]);

    tenancy()->initialize($tenantA);
    expect(Matter::query()->count())->toBe(2);

    tenancy()->initialize($tenantB);
    expect(Matter::query()->count())->toBe(4);
});
