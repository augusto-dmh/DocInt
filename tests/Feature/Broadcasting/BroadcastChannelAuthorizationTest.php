<?php

use App\Models\Client;
use App\Models\Document;
use App\Models\Matter;
use App\Models\Tenant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

beforeEach(function (): void {
    (new RolesAndPermissionsSeeder)->run();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb' => [
            'driver' => 'reverb',
            'key' => 'test-reverb-key',
            'secret' => 'test-reverb-secret',
            'app_id' => 'test-reverb-app',
            'options' => [
                'host' => 'localhost',
                'port' => 8080,
                'scheme' => 'http',
                'useTLS' => false,
            ],
            'client_options' => [],
        ],
    ]);
});

afterEach(function (): void {
    setPermissionsTeamId(null);
    tenancy()->end();
});

function authorizeBroadcastChannel(
    TestCase $testCase,
    \App\Models\User $user,
    string $channelName,
    array $session = [],
    array $headers = [],
): TestResponse {
    return $testCase
        ->withHeaders($headers)
        ->withSession($session)
        ->actingAs($user)
        ->post('/broadcasting/auth', [
            'channel_name' => $channelName,
            'socket_id' => '1234.5678',
        ]);
}

function createBroadcastAuthorizationDocument(): array
{
    $tenant = Tenant::factory()->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);
    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
    ]);

    return [$tenant, $document];
}

test('tenant member can authorize tenant and document broadcast channels', function (): void {
    [$tenant, $document] = createBroadcastAuthorizationDocument();
    $user = createTenantAdmin($tenant);

    authorizeBroadcastChannel($this, $user, "private-tenants.{$tenant->id}.documents")
        ->assertOk()
        ->assertJsonStructure(['auth']);

    authorizeBroadcastChannel($this, $user, "private-documents.{$document->id}")
        ->assertOk()
        ->assertJsonStructure(['auth']);
});

test('cross tenant member is denied tenant and document broadcast channels', function (): void {
    [$tenant, $document] = createBroadcastAuthorizationDocument();
    $otherTenant = Tenant::factory()->create();
    $user = createTenantAdmin($otherTenant);

    authorizeBroadcastChannel($this, $user, "private-tenants.{$tenant->id}.documents")
        ->assertForbidden();

    authorizeBroadcastChannel($this, $user, "private-documents.{$document->id}")
        ->assertForbidden();
});

test('super admin authorization requires matching active tenant context', function (): void {
    [$tenant, $document] = createBroadcastAuthorizationDocument();
    $superAdmin = createSuperAdmin($tenant);
    $otherTenant = Tenant::factory()->create();

    authorizeBroadcastChannel($this, $superAdmin, "private-tenants.{$tenant->id}.documents", [
        tenantContextSessionKey() => $tenant->id,
    ])->assertOk()->assertJsonStructure(['auth']);

    authorizeBroadcastChannel($this, $superAdmin, "private-documents.{$document->id}", [
        tenantContextSessionKey() => $tenant->id,
    ])->assertOk()->assertJsonStructure(['auth']);

    authorizeBroadcastChannel($this, $superAdmin, "private-tenants.{$tenant->id}.documents", [
        tenantContextSessionKey() => $otherTenant->id,
    ])->assertForbidden();

    authorizeBroadcastChannel($this, $superAdmin, "private-documents.{$document->id}", [
        tenantContextSessionKey() => $otherTenant->id,
    ])->assertForbidden();
});
