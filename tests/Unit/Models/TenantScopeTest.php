<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Document;
use App\Models\Matter;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stancl\Tenancy\Database\TenantScope;

pest()->extend(Tests\TestCase::class)->use(RefreshDatabase::class);

afterEach(function (): void {
    tenancy()->end();
});

function makeTenantScopedDocument(): Document
{
    $tenant = Tenant::factory()->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = Matter::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

    return Document::factory()->create(['tenant_id' => $tenant->id, 'matter_id' => $matter->id]);
}

test('Document::find returns null for foreign-tenant id', function (): void {
    $foreignDocument = makeTenantScopedDocument();
    $currentTenant = Tenant::factory()->create();

    tenancy()->initialize($currentTenant);

    expect(Document::find($foreignDocument->id))->toBeNull();
});

test('Document::find returns the model for matching tenant', function (): void {
    $document = makeTenantScopedDocument();
    $tenant = Tenant::query()->findOrFail($document->tenant_id);

    tenancy()->initialize($tenant);

    expect(Document::find($document->id))->not->toBeNull();
});

test('Document::withoutGlobalScope(TenantScope::class)->find bypasses scope', function (): void {
    $foreignDocument = makeTenantScopedDocument();
    $currentTenant = Tenant::factory()->create();

    tenancy()->initialize($currentTenant);

    expect(Document::withoutGlobalScope(TenantScope::class)->find($foreignDocument->id))->not->toBeNull();
});
