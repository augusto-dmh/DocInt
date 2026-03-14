<?php

use App\Models\Client;
use App\Models\Matter;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

afterEach(function (): void {
    setPermissionsTeamId(null);
    tenancy()->end();
});

test('database seeder creates the demo tenant and users', function (): void {
    $this->seed(DatabaseSeeder::class);

    $tenant = Tenant::query()
        ->where('slug', 'acme-legal')
        ->first();

    expect($tenant)->not->toBeNull()
        ->and($tenant->name)->toBe('Acme Legal');

    $users = User::query()
        ->where('tenant_id', $tenant->id)
        ->orderBy('email')
        ->get()
        ->keyBy('email');

    expect($users->keys()->all())->toEqual([
        'admin@example.com',
        'associate@example.com',
        'client@example.com',
        'partner@example.com',
    ]);

    setPermissionsTeamId($tenant->id);

    expect($users['admin@example.com']->hasRole('tenant-admin'))->toBeTrue()
        ->and($users['partner@example.com']->hasRole('partner'))->toBeTrue()
        ->and($users['associate@example.com']->hasRole('associate'))->toBeTrue()
        ->and($users['client@example.com']->hasRole('client'))->toBeTrue();
});

test('database seeder creates deterministic demo clients and matters', function (): void {
    $this->seed(DatabaseSeeder::class);

    $tenant = Tenant::query()
        ->where('slug', 'acme-legal')
        ->firstOrFail();

    expect(Client::query()->where('tenant_id', $tenant->id)->count())->toBe(4)
        ->and(Matter::query()->where('tenant_id', $tenant->id)->count())->toBe(8);
});
