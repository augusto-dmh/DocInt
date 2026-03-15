<?php

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Document;
use App\Models\Matter;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

afterEach(function (): void {
    setPermissionsTeamId(null);
    tenancy()->end();
});

test('database seeder creates the demo tenant and workspace roles', function (): void {
    $this->seed(DatabaseSeeder::class);

    $tenant = Tenant::query()
        ->where('slug', 'acme-legal')
        ->first();

    expect($tenant)->not->toBeNull()
        ->and($tenant->name)->toBe('Acme Legal');

    $tenantUsers = User::query()
        ->where('tenant_id', $tenant->id)
        ->orderBy('email')
        ->get()
        ->keyBy('email');
    $superAdmin = User::query()
        ->where('email', 'super@example.com')
        ->first();

    expect($tenantUsers->keys()->all())->toEqual([
        'admin@example.com',
        'associate@example.com',
        'client@example.com',
        'partner@example.com',
    ]);

    setPermissionsTeamId($tenant->id);

    expect($superAdmin)->not->toBeNull()
        ->and($superAdmin->hasRole('super-admin'))->toBeTrue()
        ->and($tenantUsers['admin@example.com']->hasRole('tenant-admin'))->toBeTrue()
        ->and($tenantUsers['partner@example.com']->hasRole('partner'))->toBeTrue()
        ->and($tenantUsers['associate@example.com']->hasRole('associate'))->toBeTrue()
        ->and($tenantUsers['client@example.com']->hasRole('client'))->toBeTrue();
});

test('database seeder creates deterministic demo clients, matters, and documents', function (): void {
    $this->seed(DatabaseSeeder::class);

    $tenant = Tenant::query()
        ->where('slug', 'acme-legal')
        ->firstOrFail();

    expect(Client::query()->where('tenant_id', $tenant->id)->count())->toBe(4)
        ->and(Matter::query()->where('tenant_id', $tenant->id)->count())->toBe(8)
        ->and(Document::query()->where('tenant_id', $tenant->id)->count())->toBe(12)
        ->and(AuditLog::query()->where('tenant_id', $tenant->id)->count())->toBe(36)
        ->and(Matter::query()->where('tenant_id', $tenant->id)->doesntHave('documents')->exists())->toBeTrue()
        ->and(Matter::query()->where('tenant_id', $tenant->id)->has('documents')->exists())->toBeTrue()
        ->and(Document::query()->where('tenant_id', $tenant->id)->where('status', 'uploaded')->exists())->toBeTrue()
        ->and(Document::query()->where('tenant_id', $tenant->id)->where('status', 'ready_for_review')->exists())->toBeTrue()
        ->and(Document::query()->where('tenant_id', $tenant->id)->where('status', 'approved')->exists())->toBeTrue();
});
