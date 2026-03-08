<?php

use App\Enums\MatterStatus;
use App\Models\Client;
use App\Models\Matter;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\HasMany;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->matter = Matter::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $this->client->id]);
});

afterEach(function (): void {
    tenancy()->end();
});

test('matter factory creates a valid matter', function (): void {
    expect($this->matter->tenant_id)->toBe($this->tenant->id)
        ->and($this->matter->client_id)->toBe($this->client->id)
        ->and($this->matter->status)->toBe(MatterStatus::Open);
});

test('matter belongs to a client', function (): void {
    expect($this->matter->client->id)->toBe($this->client->id);
});

test('matter has many documents relationship', function (): void {
    expect($this->matter->documents())->toBeInstanceOf(HasMany::class);
});

test('matter closed state sets status to closed', function (): void {
    $matter = Matter::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'client_id' => $this->client->id]);

    expect($matter->status)->toBe(MatterStatus::Closed);
});

test('matter on hold state sets status to on hold', function (): void {
    $matter = Matter::factory()->onHold()->create(['tenant_id' => $this->tenant->id, 'client_id' => $this->client->id]);

    expect($matter->status)->toBe(MatterStatus::OnHold);
});

test('matter query is scoped to current tenant', function (): void {
    $tenantB = Tenant::factory()->create();
    $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);

    Matter::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $this->client->id]);
    Matter::factory()->count(4)->create(['tenant_id' => $tenantB->id, 'client_id' => $clientB->id]);

    tenancy()->initialize($this->tenant);
    expect(Matter::query()->count())->toBe(2);

    tenancy()->initialize($tenantB);
    expect(Matter::query()->count())->toBe(4);
});
