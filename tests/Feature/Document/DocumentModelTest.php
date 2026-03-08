<?php

use App\Enums\DocumentStatus;
use App\Models\Client;
use App\Models\Document;
use App\Models\Matter;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->matter = Matter::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $this->client->id]);
});

afterEach(function (): void {
    tenancy()->end();
});

test('document factory creates a valid document', function (): void {
    $document = Document::factory()->create(['tenant_id' => $this->tenant->id, 'matter_id' => $this->matter->id]);

    expect($document->tenant_id)->toBe($this->tenant->id)
        ->and($document->matter_id)->toBe($this->matter->id)
        ->and($document->status)->toBe(DocumentStatus::Uploaded);
});

test('document belongs to a matter', function (): void {
    $document = Document::factory()->create(['tenant_id' => $this->tenant->id, 'matter_id' => $this->matter->id]);

    expect($document->matter->id)->toBe($this->matter->id);
});

test('document uploader relationship resolves to user', function (): void {
    $user = User::factory()->forTenant($this->tenant)->create();

    $document = Document::factory()->create([
        'tenant_id' => $this->tenant->id,
        'matter_id' => $this->matter->id,
        'uploaded_by' => $user->id,
    ]);

    expect($document->uploader->id)->toBe($user->id);
});

test('document approved state sets status to approved', function (): void {
    $document = Document::factory()->approved()->create(['tenant_id' => $this->tenant->id, 'matter_id' => $this->matter->id]);

    expect($document->status)->toBe(DocumentStatus::Approved);
});

test('document ready for review state sets status to ready for review', function (): void {
    $document = Document::factory()->readyForReview()->create(['tenant_id' => $this->tenant->id, 'matter_id' => $this->matter->id]);

    expect($document->status)->toBe(DocumentStatus::ReadyForReview);
});

test('document query is scoped to current tenant', function (): void {
    $tenantB = Tenant::factory()->create();
    $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);
    $matterB = Matter::factory()->create(['tenant_id' => $tenantB->id, 'client_id' => $clientB->id]);

    Document::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'matter_id' => $this->matter->id]);
    Document::factory()->count(5)->create(['tenant_id' => $tenantB->id, 'matter_id' => $matterB->id]);

    tenancy()->initialize($this->tenant);
    expect(Document::query()->count())->toBe(2);

    tenancy()->initialize($tenantB);
    expect(Document::query()->count())->toBe(5);
});
