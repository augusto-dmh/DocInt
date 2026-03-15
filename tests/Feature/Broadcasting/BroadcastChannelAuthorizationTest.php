<?php

use App\Broadcasting\BroadcastChannelAuthorizer;
use App\Models\Client;
use App\Models\Document;
use App\Models\Matter;
use App\Models\Tenant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

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
    session()->flush();
});

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

test('broadcast auth route uses the tenant-safe middleware stack', function (): void {
    $route = app('router')->getRoutes()->match(Request::create('/broadcasting/auth', 'POST'));

    expect($route->gatherMiddleware())->toContain('web', 'auth', 'verified', 'tenant');
});

test('tenant member can authorize tenant and document broadcast channels', function (): void {
    [$tenant, $document] = createBroadcastAuthorizationDocument();
    $user = createTenantAdmin($tenant);
    $authorizer = app(BroadcastChannelAuthorizer::class);

    expect($authorizer->canAccessTenantDocumentsChannel($user, $tenant->id))->toBeTrue()
        ->and($authorizer->canAccessDocumentChannel($user, $document->id))->toBeTrue();
});

test('cross tenant member is denied tenant and document broadcast channels', function (): void {
    [$tenant, $document] = createBroadcastAuthorizationDocument();
    $otherTenant = Tenant::factory()->create();
    $user = createTenantAdmin($otherTenant);
    $authorizer = app(BroadcastChannelAuthorizer::class);

    expect($authorizer->canAccessTenantDocumentsChannel($user, $tenant->id))->toBeFalse()
        ->and($authorizer->canAccessDocumentChannel($user, $document->id))->toBeFalse();
});

test('super admin authorization requires matching active tenant context', function (): void {
    [$tenant, $document] = createBroadcastAuthorizationDocument();
    $superAdmin = createSuperAdmin($tenant);
    $otherTenant = Tenant::factory()->create();
    $authorizer = app(BroadcastChannelAuthorizer::class);

    session()->put(tenantContextSessionKey(), $tenant->id);

    expect($authorizer->canAccessTenantDocumentsChannel($superAdmin, $tenant->id))->toBeTrue()
        ->and($authorizer->canAccessDocumentChannel($superAdmin, $document->id))->toBeTrue();

    session()->put(tenantContextSessionKey(), $otherTenant->id);

    expect($authorizer->canAccessTenantDocumentsChannel($superAdmin, $tenant->id))->toBeFalse()
        ->and($authorizer->canAccessDocumentChannel($superAdmin, $document->id))->toBeFalse();
});
