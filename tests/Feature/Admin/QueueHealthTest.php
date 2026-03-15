<?php

use App\Models\Tenant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    (new RolesAndPermissionsSeeder)->run();
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
});

afterEach(function (): void {
    tenancy()->end();
    setPermissionsTeamId(null);
});

test('super-admin can access queue health page without tenant context', function (): void {
    $tenant = Tenant::factory()->create();
    $superAdmin = createSuperAdmin($tenant);

    Http::fake([
        '*' => Http::response(['message' => 'Service unavailable'], 503),
    ]);

    $this->actingAs($superAdmin)
        ->get(route('admin.queue-health'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/QueueHealth')
            ->where('queueHealth.available', false)
            ->where('queueHealth.queues', [])
            ->where('queueHealth.summary.total_messages', 0)
            ->where('queueHealth.error', 'Queue health metrics are currently unavailable.')
        );
});

test('non super-admin users cannot access queue health page', function (): void {
    $tenant = Tenant::factory()->create();
    $tenantAdmin = createTenantAdmin($tenant);

    $this->actingAs($tenantAdmin)
        ->get(route('admin.queue-health'))
        ->assertForbidden();
});

test('queue health page renders metrics from rabbitmq management api response', function (): void {
    $tenant = Tenant::factory()->create();
    $superAdmin = createSuperAdmin($tenant);

    Http::fake([
        '*' => Http::response([
            [
                'name' => 'queue.virus-scan',
                'messages' => 4,
                'messages_ready' => 2,
                'messages_unacknowledged' => 2,
                'consumers' => 2,
                'state' => 'running',
            ],
            [
                'name' => 'queue.dead-letters',
                'messages' => 3,
                'messages_ready' => 3,
                'messages_unacknowledged' => 0,
                'consumers' => 1,
                'state' => 'running',
            ],
        ], 200),
    ]);

    $this->actingAs($superAdmin)
        ->get(route('admin.queue-health'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/QueueHealth')
            ->where('queueHealth.available', true)
            ->where('queueHealth.error', null)
            ->where('queueHealth.summary.total_messages', 7)
            ->where('queueHealth.summary.total_ready', 5)
            ->where('queueHealth.summary.total_unacked', 2)
            ->where('queueHealth.summary.total_consumers', 3)
            ->where('queueHealth.summary.dead_letter_messages', 3)
            ->has('queueHealth.queues', 10)
            ->where('queueHealth.queues.0.name', 'queue.virus-scan')
            ->where('queueHealth.queues.0.messages', 4)
            ->where('queueHealth.queues.0.is_dead_letter', false)
            ->where('queueHealth.queues.9.name', 'queue.dead-letters')
            ->where('queueHealth.queues.9.messages', 3)
            ->where('queueHealth.queues.9.is_dead_letter', true)
        );
});

test('queue health page handles management api failure gracefully', function (): void {
    $tenant = Tenant::factory()->create();
    $superAdmin = createSuperAdmin($tenant);

    Http::fake([
        '*' => Http::response(['message' => 'Service unavailable'], 503),
    ]);

    $this->actingAs($superAdmin)
        ->get(route('admin.queue-health'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/QueueHealth')
            ->where('queueHealth.available', false)
            ->where('queueHealth.queues', [])
            ->where('queueHealth.summary.total_messages', 0)
            ->where('queueHealth.error', 'Queue health metrics are currently unavailable.')
        );
});
