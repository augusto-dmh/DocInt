<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Document;
use App\Models\Matter;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    (new RolesAndPermissionsSeeder)->run();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function (): void {
    setPermissionsTeamId(null);
    tenancy()->end();
});

function policyFixtures(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = Matter::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
    $document = Document::factory()->create(['tenant_id' => $tenant->id, 'matter_id' => $matter->id]);

    return [$tenant, $user, $document];
}

function grantPermissionTo(User $user, Tenant $tenant, string $permission): void
{
    setPermissionsTeamId($tenant->id);
    $user->givePermissionTo($permission);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

function policyArguments(string $method, Document $document): array
{
    return in_array($method, ['viewAny', 'create'], true) ? [Document::class] : [$document];
}

dataset('policyMethodPermission', [
    'viewAny needs view documents' => ['viewAny', 'view documents'],
    'view needs view documents' => ['view', 'view documents'],
    'create needs create documents' => ['create', 'create documents'],
    'update needs edit documents' => ['update', 'edit documents'],
    'delete needs delete documents' => ['delete', 'delete documents'],
    'review needs review documents' => ['review', 'review documents'],
    'approve needs approve documents' => ['approve', 'approve documents'],
    'annotate needs edit documents' => ['annotate', 'edit documents'],
    'assignReviewer needs manage users' => ['assignReviewer', 'manage users'],
    'comment needs edit documents' => ['comment', 'edit documents'],
    'moderateComments needs approve documents' => ['moderateComments', 'approve documents'],
]);

test('grants when user holds the required permission', function (string $method, string $permission): void {
    [$tenant, $user, $document] = policyFixtures();
    grantPermissionTo($user, $tenant, $permission);

    expect(Gate::forUser($user)->allows($method, policyArguments($method, $document)))->toBeTrue();
})->with('policyMethodPermission');

test('denies when user lacks the required permission', function (string $method, string $permission): void {
    [, $user, $document] = policyFixtures();

    expect(Gate::forUser($user)->allows($method, policyArguments($method, $document)))->toBeFalse();
})->with('policyMethodPermission');

test('superadmin bypasses every DocumentPolicy gate without explicit permissions', function (): void {
    [$tenant, $user, $document] = policyFixtures();
    setPermissionsTeamId($tenant->id);
    $user->assignRole('super-admin');

    $methods = [
        'viewAny', 'view', 'create', 'update', 'delete',
        'review', 'approve', 'annotate', 'assignReviewer',
        'comment', 'moderateComments',
    ];

    foreach ($methods as $method) {
        expect(Gate::forUser($user)->allows($method, policyArguments($method, $document)))
            ->toBeTrue("Gate::before superadmin bypass failed for DocumentPolicy::{$method}");
    }
});
