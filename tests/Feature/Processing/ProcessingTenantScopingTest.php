<?php

use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentClassification;
use App\Models\ExtractedData;
use App\Models\Matter;
use App\Models\ProcessingEvent;
use App\Models\Tenant;

afterEach(function (): void {
    tenancy()->end();
});

test('processing models respect tenant scoping', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $clientA = Client::factory()->create(['tenant_id' => $tenantA->id]);
    $matterA = Matter::factory()->create([
        'tenant_id' => $tenantA->id,
        'client_id' => $clientA->id,
    ]);
    $documentA = Document::factory()->create([
        'tenant_id' => $tenantA->id,
        'matter_id' => $matterA->id,
    ]);

    $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);
    $matterB = Matter::factory()->create([
        'tenant_id' => $tenantB->id,
        'client_id' => $clientB->id,
    ]);
    $documentB = Document::factory()->create([
        'tenant_id' => $tenantB->id,
        'matter_id' => $matterB->id,
    ]);

    ProcessingEvent::factory()->create([
        'tenant_id' => $tenantA->id,
        'document_id' => $documentA->id,
    ]);
    ExtractedData::factory()->create([
        'tenant_id' => $tenantA->id,
        'document_id' => $documentA->id,
    ]);
    DocumentClassification::factory()->create([
        'tenant_id' => $tenantA->id,
        'document_id' => $documentA->id,
    ]);

    ProcessingEvent::factory()->create([
        'tenant_id' => $tenantB->id,
        'document_id' => $documentB->id,
    ]);
    ExtractedData::factory()->create([
        'tenant_id' => $tenantB->id,
        'document_id' => $documentB->id,
    ]);
    DocumentClassification::factory()->create([
        'tenant_id' => $tenantB->id,
        'document_id' => $documentB->id,
    ]);

    tenancy()->initialize($tenantA);

    expect(ProcessingEvent::query()->count())->toBe(1)
        ->and(ExtractedData::query()->count())->toBe(1)
        ->and(DocumentClassification::query()->count())->toBe(1);

    tenancy()->initialize($tenantB);

    expect(ProcessingEvent::query()->count())->toBe(1)
        ->and(ExtractedData::query()->count())->toBe(1)
        ->and(DocumentClassification::query()->count())->toBe(1);
});
