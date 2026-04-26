<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Models\DocumentClassification;
use App\Models\ExtractedData;
use App\Models\ProcessingEvent;
use App\Services\Documents\DocumentShowPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

pest()->extend(Tests\TestCase::class)->use(RefreshDatabase::class);

test('formatProcessingEvent serialises an event with status fields and ISO timestamp', function (): void {
    $event = (new ProcessingEvent)->fill([
        'consumer_name' => 'manual-review',
        'status_from' => DocumentStatus::ReadyForReview->value,
        'status_to' => DocumentStatus::Reviewed->value,
        'event' => 'document.status.transitioned',
    ]);
    $event->id = 42;
    $event->created_at = Carbon::parse('2025-01-15T10:30:00Z');

    $presenter = new DocumentShowPresenter;

    expect($presenter->formatProcessingEvent($event))->toBe([
        'id' => 42,
        'consumer_name' => 'manual-review',
        'status_from' => 'ready_for_review',
        'status_to' => 'reviewed',
        'event' => 'document.status.transitioned',
        'created_at' => '2025-01-15T10:30:00.000000Z',
    ]);
});

test('formatProcessingEvent preserves null status_from and status_to', function (): void {
    $event = (new ProcessingEvent)->fill([
        'consumer_name' => 'upload-dispatch',
        'event' => 'document.uploaded',
    ]);
    $event->id = 7;
    $event->status_from = null;
    $event->status_to = null;
    $event->created_at = Carbon::parse('2025-02-02T00:00:00Z');

    $presenter = new DocumentShowPresenter;

    $payload = $presenter->formatProcessingEvent($event);

    expect($payload['status_from'])->toBeNull()
        ->and($payload['status_to'])->toBeNull();
});

test('formatExtractedData returns null for a missing record', function (): void {
    $presenter = new DocumentShowPresenter;

    expect($presenter->formatExtractedData(null))->toBeNull();
});

test('formatExtractedData serialises payload, metadata, and ISO timestamps', function (): void {
    $extracted = (new ExtractedData)->fill([
        'provider' => 'openai',
        'extracted_text' => 'Hello world.',
        'payload' => ['lines' => ['Hello', 'world']],
        'metadata' => ['language' => 'en'],
    ]);
    $extracted->created_at = Carbon::parse('2025-01-01T00:00:00Z');
    $extracted->updated_at = Carbon::parse('2025-01-01T01:00:00Z');

    $presenter = new DocumentShowPresenter;

    expect($presenter->formatExtractedData($extracted))->toBe([
        'provider' => 'openai',
        'extracted_text' => 'Hello world.',
        'payload' => ['lines' => ['Hello', 'world']],
        'metadata' => ['language' => 'en'],
        'created_at' => '2025-01-01T00:00:00.000000Z',
        'updated_at' => '2025-01-01T01:00:00.000000Z',
    ]);
});

test('formatExtractedData coerces non-array payload and metadata to null', function (): void {
    $extracted = (new ExtractedData)->fill([
        'provider' => 'openai',
        'extracted_text' => null,
    ]);
    $extracted->payload = 'not-an-array';
    $extracted->metadata = null;
    $extracted->created_at = Carbon::parse('2025-01-01T00:00:00Z');
    $extracted->updated_at = Carbon::parse('2025-01-01T00:00:00Z');

    $presenter = new DocumentShowPresenter;

    $payload = $presenter->formatExtractedData($extracted);

    expect($payload['payload'])->toBeNull()
        ->and($payload['metadata'])->toBeNull()
        ->and($payload['extracted_text'])->toBeNull();
});

test('formatClassification returns null for a missing record', function (): void {
    $presenter = new DocumentShowPresenter;

    expect($presenter->formatClassification(null))->toBeNull();
});

test('formatClassification casts confidence to float and serialises metadata', function (): void {
    $classification = (new DocumentClassification)->fill([
        'provider' => 'openai',
        'type' => 'contract',
        'confidence' => '0.875',
        'metadata' => ['rationale' => 'matched headings'],
    ]);
    $classification->created_at = Carbon::parse('2025-03-01T12:00:00Z');
    $classification->updated_at = Carbon::parse('2025-03-01T12:30:00Z');

    $presenter = new DocumentShowPresenter;

    expect($presenter->formatClassification($classification))->toBe([
        'provider' => 'openai',
        'type' => 'contract',
        'confidence' => 0.875,
        'metadata' => ['rationale' => 'matched headings'],
        'created_at' => '2025-03-01T12:00:00.000000Z',
        'updated_at' => '2025-03-01T12:30:00.000000Z',
    ]);
});

test('formatClassification preserves a null confidence as null', function (): void {
    $classification = (new DocumentClassification)->fill([
        'provider' => 'openai',
        'type' => 'general',
        'metadata' => null,
    ]);
    $classification->confidence = null;
    $classification->created_at = Carbon::parse('2025-03-01T12:00:00Z');
    $classification->updated_at = Carbon::parse('2025-03-01T12:00:00Z');

    $presenter = new DocumentShowPresenter;

    $payload = $presenter->formatClassification($classification);

    expect($payload['confidence'])->toBeNull()
        ->and($payload['metadata'])->toBeNull();
});
