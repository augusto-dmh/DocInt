<?php

use App\Events\DocumentProcessingEvent;
use App\Models\Client;
use App\Models\Matter;
use App\Models\ProcessingEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DocumentUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

afterEach(function (): void {
    tenancy()->end();
});

test('document upload assigns trace id dispatches processing event and stores traceable log', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);

    Storage::fake('s3');
    Event::fake([DocumentProcessingEvent::class]);

    $document = app(DocumentUploadService::class)->upload(
        file: UploadedFile::fake()->create('retainer.pdf', 256, 'application/pdf'),
        matter: $matter,
        user: $user,
        title: 'Retainer Agreement',
    );

    $processingEvent = ProcessingEvent::query()
        ->where('document_id', $document->id)
        ->where('consumer_name', 'upload-dispatch')
        ->first();

    expect($document->processing_trace_id)->not()->toBeNull()
        ->and(Str::isUuid($document->processing_trace_id))->toBeTrue()
        ->and($processingEvent)->not()->toBeNull()
        ->and($processingEvent?->trace_id)->toBe($document->processing_trace_id)
        ->and($processingEvent?->event)->toBe('document.uploaded')
        ->and($processingEvent?->status_to)->toBe('uploaded');

    Event::assertDispatched(DocumentProcessingEvent::class, function (DocumentProcessingEvent $event) use ($document): bool {
        $payload = $event->toPayload();

        return $event->documentId === $document->id
            && $event->tenantId === $document->tenant_id
            && $event->traceId === $document->processing_trace_id
            && $event->event === 'document.uploaded'
            && Str::isUuid($event->messageId)
            && array_key_exists('message_id', $payload)
            && array_key_exists('trace_id', $payload)
            && array_key_exists('tenant_id', $payload)
            && array_key_exists('document_id', $payload)
            && array_key_exists('event', $payload)
            && array_key_exists('timestamp', $payload)
            && array_key_exists('metadata', $payload)
            && array_key_exists('retry_count', $payload);
    });
});
