<?php

use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('docker compose defines the reverb runtime service contract', function (): void {
    $compose = file_get_contents(base_path('docker-compose.yaml'));

    expect($compose)->toBeString()
        ->and($compose)->toContain('reverb:')
        ->and($compose)->toContain('docker/reverb/Dockerfile')
        ->and($compose)->toContain('container_name: docintern-reverb')
        ->and($compose)->toContain('- "8080:8080"')
        ->and($compose)->toContain('BROADCAST_CONNECTION: reverb');
});

test('.env example exposes reverb runtime defaults', function (): void {
    $environmentExample = file_get_contents(base_path('.env.example'));

    expect($environmentExample)->toBeString()
        ->and($environmentExample)->toContain('BROADCAST_CONNECTION=reverb')
        ->and($environmentExample)->toContain('REVERB_APP_ID=docintern')
        ->and($environmentExample)->toContain('REVERB_HOST=reverb')
        ->and($environmentExample)->toContain('REVERB_PORT=8080')
        ->and($environmentExample)->toContain('VITE_REVERB_HOST=localhost')
        ->and($environmentExample)->toContain('VITE_REVERB_PORT=8080');
});

test('shared inertia data exposes the realtime config payload', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();

    config([
        'broadcasting.default' => 'null',
        'reverb.client' => [
            'app_key' => 'test-reverb-key',
            'host' => 'localhost',
            'port' => 8080,
            'scheme' => 'http',
        ],
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('realtime.enabled', false)
            ->where('realtime.broadcaster', 'null')
            ->where('realtime.appKey', 'test-reverb-key')
            ->where('realtime.host', 'localhost')
            ->where('realtime.port', 8080)
            ->where('realtime.scheme', 'http')
            ->where('realtime.channels.tenantDocumentsPattern', 'tenants.{tenantId}.documents')
            ->where('realtime.channels.documentPattern', 'documents.{documentId}')
        );
});
