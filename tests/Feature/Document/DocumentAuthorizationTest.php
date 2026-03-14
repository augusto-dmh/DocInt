<?php

use App\Models\Client;
use App\Models\Document;
use App\Models\Matter;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DocumentUploadService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    (new RolesAndPermissionsSeeder)->run();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function (): void {
    setPermissionsTeamId(null);
    tenancy()->end();
});

function createDocumentAuthContext(string $role): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);
    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
    ]);

    setPermissionsTeamId($tenant->id);
    $user->assignRole($role);
    setPermissionsTeamId(null);

    return [$tenant, $user, $matter, $document];
}

describe('tenant-admin', function (): void {
    test('can access document CRUD routes', function (): void {
        [$tenant, $user, $matter, $document] = createDocumentAuthContext('tenant-admin');
        Storage::fake('s3');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('documents.index'))
            ->assertSuccessful();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('matters.documents.create', $matter))
            ->assertSuccessful();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->post(route('matters.documents.store', $matter), [
                'title' => 'Tenant Admin Upload',
                'file' => UploadedFile::fake()->create('tenant-admin.pdf', 12, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('documents.show', $document))
            ->assertSuccessful();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('documents.edit', $document))
            ->assertSuccessful();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->put(route('documents.update', $document), ['title' => 'Updated'])
            ->assertRedirect(route('documents.show', $document));

        $deletableDocument = Document::factory()->create([
            'tenant_id' => $tenant->id,
            'matter_id' => $matter->id,
            'uploaded_by' => $user->id,
            'file_path' => 'tenants/'.$tenant->id.'/documents/cleanup/delete-me.pdf',
        ]);
        Storage::disk('s3')->put($deletableDocument->file_path, 'delete me');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->delete(route('documents.destroy', $deletableDocument))
            ->assertRedirect(route('documents.index'));
    });

    test('can download documents', function (): void {
        [$tenant, $user, , $document] = createDocumentAuthContext('tenant-admin');
        $downloadUrl = 'https://example.test/tenant-admin-document';

        $this->mock(DocumentUploadService::class, function (MockInterface $mock) use ($document, $downloadUrl): void {
            $mock->shouldReceive('generatePresignedUrl')
                ->once()
                ->withArgs(fn (Document $resolvedDocument): bool => $resolvedDocument->is($document))
                ->andReturn($downloadUrl);
        });

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('documents.download', $document))
            ->assertRedirect($downloadUrl);
    });
});

describe('partner', function (): void {
    test('can view create edit and update but cannot delete documents', function (): void {
        [$tenant, $user, $matter, $document] = createDocumentAuthContext('partner');
        Storage::fake('s3');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('documents.index'))
            ->assertSuccessful();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('matters.documents.create', $matter))
            ->assertSuccessful();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('documents.show', $document))
            ->assertSuccessful();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('documents.edit', $document))
            ->assertSuccessful();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->put(route('documents.update', $document), ['title' => 'Partner Update'])
            ->assertRedirect(route('documents.show', $document));

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->delete(route('documents.destroy', $document))
            ->assertForbidden();
    });

    test('can download documents', function (): void {
        [$tenant, $user, , $document] = createDocumentAuthContext('partner');
        $downloadUrl = 'https://example.test/partner-document';

        $this->mock(DocumentUploadService::class, function (MockInterface $mock) use ($document, $downloadUrl): void {
            $mock->shouldReceive('generatePresignedUrl')
                ->once()
                ->withArgs(fn (Document $resolvedDocument): bool => $resolvedDocument->is($document))
                ->andReturn($downloadUrl);
        });

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('documents.download', $document))
            ->assertRedirect($downloadUrl);
    });
});

describe('associate', function (): void {
    test('can view create edit and update but cannot delete documents', function (): void {
        [$tenant, $user, $matter, $document] = createDocumentAuthContext('associate');
        Storage::fake('s3');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('documents.index'))
            ->assertSuccessful();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('matters.documents.create', $matter))
            ->assertSuccessful();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('documents.show', $document))
            ->assertSuccessful();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('documents.edit', $document))
            ->assertSuccessful();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->put(route('documents.update', $document), ['title' => 'Associate Update'])
            ->assertRedirect(route('documents.show', $document));

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->delete(route('documents.destroy', $document))
            ->assertForbidden();
    });

    test('can download documents', function (): void {
        [$tenant, $user, , $document] = createDocumentAuthContext('associate');
        $downloadUrl = 'https://example.test/associate-document';

        $this->mock(DocumentUploadService::class, function (MockInterface $mock) use ($document, $downloadUrl): void {
            $mock->shouldReceive('generatePresignedUrl')
                ->once()
                ->withArgs(fn (Document $resolvedDocument): bool => $resolvedDocument->is($document))
                ->andReturn($downloadUrl);
        });

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('documents.download', $document))
            ->assertRedirect($downloadUrl);
    });
});

describe('client role', function (): void {
    test('can view but cannot create edit update or delete documents', function (): void {
        [$tenant, $user, $matter, $document] = createDocumentAuthContext('client');
        Storage::fake('s3');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('documents.index'))
            ->assertSuccessful();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('documents.show', $document))
            ->assertSuccessful();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('matters.documents.create', $matter))
            ->assertForbidden();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->post(route('matters.documents.store', $matter), [
                'title' => 'Client Upload',
                'file' => UploadedFile::fake()->create('client.pdf', 12, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('documents.edit', $document))
            ->assertForbidden();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->put(route('documents.update', $document), ['title' => 'Forbidden'])
            ->assertForbidden();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->delete(route('documents.destroy', $document))
            ->assertForbidden();
    });

    test('can download documents', function (): void {
        [$tenant, $user, , $document] = createDocumentAuthContext('client');
        $downloadUrl = 'https://example.test/client-document';

        $this->mock(DocumentUploadService::class, function (MockInterface $mock) use ($document, $downloadUrl): void {
            $mock->shouldReceive('generatePresignedUrl')
                ->once()
                ->withArgs(fn (Document $resolvedDocument): bool => $resolvedDocument->is($document))
                ->andReturn($downloadUrl);
        });

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('documents.download', $document))
            ->assertRedirect($downloadUrl);
    });
});
