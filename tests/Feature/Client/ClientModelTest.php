<?php

use App\Models\Client;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\HasMany;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
});

afterEach(function (): void {
    tenancy()->end();
});

test('client factory creates a valid client', function (): void {
    expect($this->client->tenant_id)->toBe($this->tenant->id)
        ->and($this->client->name)->not->toBeEmpty();
});

test('client belongs to a tenant', function (): void {
    expect($this->client->tenant->id)->toBe($this->tenant->id);
});

test('client has many matters relationship', function (): void {
    expect($this->client->matters())->toBeInstanceOf(HasMany::class);
});

test('client query is scoped to current tenant', function (): void {
    $tenantB = Tenant::factory()->create();

    Client::factory()->create(['tenant_id' => $this->tenant->id]);
    Client::factory()->count(3)->create(['tenant_id' => $tenantB->id]);

    tenancy()->initialize($this->tenant);
    expect(Client::query()->count())->toBe(2);

    tenancy()->initialize($tenantB);
    expect(Client::query()->count())->toBe(3);
});
