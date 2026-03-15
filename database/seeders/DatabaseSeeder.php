<?php

namespace Database\Seeders;

use App\Enums\MatterStatus;
use App\Enums\DocumentStatus;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Document;
use App\Models\Matter;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Carbon\CarbonInterface;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        setPermissionsTeamId(null);
        $this->call(RolesAndPermissionsSeeder::class);

        $tenant = Tenant::factory()->create([
            'name' => 'Acme Legal',
            'slug' => 'acme-legal',
        ]);

        $workspaceUsers = $this->seedWorkspaceUsers($tenant);

        $clients = [
            [
                'name' => 'Atlas Manufacturing',
                'email' => 'legal@atlas.example.com',
                'phone' => '555-0101',
                'company' => 'Atlas Manufacturing',
                'notes' => 'Primary commercial contracts client.',
                'matters' => [
                    [
                        'title' => 'Vendor Agreement Review',
                        'description' => 'Review master services agreement for strategic supplier.',
                        'reference_number' => 'ACME-001',
                        'status' => MatterStatus::Open,
                        'documents' => [
                            [
                                'title' => 'Master Services Agreement Draft',
                                'file_name' => 'atlas-msa-draft.pdf',
                                'status' => 'uploaded',
                                'uploaded_by' => 'associate',
                                'days_ago' => 8,
                            ],
                            [
                                'title' => 'Redline Summary',
                                'file_name' => 'atlas-redline-summary.pdf',
                                'status' => 'ready_for_review',
                                'uploaded_by' => 'partner',
                                'days_ago' => 6,
                            ],
                        ],
                    ],
                    [
                        'title' => 'Employment Policy Update',
                        'description' => 'Refresh handbook and termination procedures.',
                        'reference_number' => 'ACME-002',
                        'status' => MatterStatus::OnHold,
                        'documents' => [],
                    ],
                ],
            ],
            [
                'name' => 'Bluebird Health',
                'email' => 'counsel@bluebird.example.com',
                'phone' => '555-0102',
                'company' => 'Bluebird Health',
                'notes' => 'Healthcare regulatory advisory work.',
                'matters' => [
                    [
                        'title' => 'Clinic Acquisition Due Diligence',
                        'description' => 'Coordinate diligence checklist for clinic acquisition.',
                        'reference_number' => 'ACME-003',
                        'status' => MatterStatus::Open,
                        'documents' => [
                            [
                                'title' => 'Acquisition Checklist',
                                'file_name' => 'bluebird-acquisition-checklist.pdf',
                                'status' => 'approved',
                                'uploaded_by' => 'tenant-admin',
                                'days_ago' => 12,
                            ],
                            [
                                'title' => 'Licensing Review Notes',
                                'file_name' => 'bluebird-licensing-review.pdf',
                                'status' => 'ready_for_review',
                                'uploaded_by' => 'associate',
                                'days_ago' => 10,
                            ],
                            [
                                'title' => 'Provider Contract Matrix',
                                'file_name' => 'bluebird-provider-contract-matrix.pdf',
                                'status' => 'uploaded',
                                'uploaded_by' => 'partner',
                                'days_ago' => 9,
                            ],
                        ],
                    ],
                    [
                        'title' => 'Privacy Complaint Response',
                        'description' => 'Prepare response package for patient privacy complaint.',
                        'reference_number' => 'ACME-004',
                        'status' => MatterStatus::Closed,
                        'documents' => [
                            [
                                'title' => 'Complaint Intake Packet',
                                'file_name' => 'bluebird-complaint-intake.pdf',
                                'status' => 'approved',
                                'uploaded_by' => 'partner',
                                'days_ago' => 16,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Cinder Logistics',
                'email' => 'ops@cinder.example.com',
                'phone' => '555-0103',
                'company' => 'Cinder Logistics',
                'notes' => 'Transportation and warehousing support.',
                'matters' => [
                    [
                        'title' => 'Warehouse Lease Negotiation',
                        'description' => 'Negotiate renewal terms for the east hub lease.',
                        'reference_number' => 'ACME-005',
                        'status' => MatterStatus::Open,
                        'documents' => [
                            [
                                'title' => 'Lease Renewal Proposal',
                                'file_name' => 'cinder-lease-renewal-proposal.pdf',
                                'status' => 'ready_for_review',
                                'uploaded_by' => 'tenant-admin',
                                'days_ago' => 5,
                            ],
                            [
                                'title' => 'Site Inspection Memo',
                                'file_name' => 'cinder-site-inspection-memo.pdf',
                                'status' => 'uploaded',
                                'uploaded_by' => 'associate',
                                'days_ago' => 4,
                            ],
                        ],
                    ],
                    [
                        'title' => 'Insurance Coverage Review',
                        'description' => 'Assess cargo and fleet coverage after policy renewal.',
                        'reference_number' => 'ACME-006',
                        'status' => MatterStatus::OnHold,
                        'documents' => [],
                    ],
                ],
            ],
            [
                'name' => 'Delta Studio Group',
                'email' => 'team@delta.example.com',
                'phone' => '555-0104',
                'company' => 'Delta Studio Group',
                'notes' => 'Creative agency with recurring IP and contract matters.',
                'matters' => [
                    [
                        'title' => 'Trademark Filing Strategy',
                        'description' => 'File marks for new product line launch.',
                        'reference_number' => 'ACME-007',
                        'status' => MatterStatus::Closed,
                        'documents' => [
                            [
                                'title' => 'Trademark Clearance Search',
                                'file_name' => 'delta-trademark-clearance-search.pdf',
                                'status' => 'approved',
                                'uploaded_by' => 'tenant-admin',
                                'days_ago' => 14,
                            ],
                        ],
                    ],
                    [
                        'title' => 'Agency Master Services Agreement',
                        'description' => 'Standardize MSA terms for enterprise clients.',
                        'reference_number' => 'ACME-008',
                        'status' => MatterStatus::Open,
                        'documents' => [
                            [
                                'title' => 'Agency MSA Working Draft',
                                'file_name' => 'delta-agency-msa-working-draft.pdf',
                                'status' => 'uploaded',
                                'uploaded_by' => 'associate',
                                'days_ago' => 3,
                            ],
                            [
                                'title' => 'Fallback Clause Review',
                                'file_name' => 'delta-fallback-clause-review.pdf',
                                'status' => 'ready_for_review',
                                'uploaded_by' => 'partner',
                                'days_ago' => 2,
                            ],
                            [
                                'title' => 'Approved Negotiation Playbook',
                                'file_name' => 'delta-negotiation-playbook.pdf',
                                'status' => 'approved',
                                'uploaded_by' => 'tenant-admin',
                                'days_ago' => 1,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($clients as $clientAttributes) {
            $matters = $clientAttributes['matters'];
            unset($clientAttributes['matters']);

            $client = Client::factory()->create([
                ...$clientAttributes,
                'tenant_id' => $tenant->id,
            ]);

            foreach ($matters as $matterAttributes) {
                $documents = $matterAttributes['documents'];
                unset($matterAttributes['documents']);

                $matter = Matter::factory()->create([
                    ...$matterAttributes,
                    'tenant_id' => $tenant->id,
                    'client_id' => $client->id,
                ]);

                $this->seedMatterDocuments($tenant, $matter, $documents, $workspaceUsers);
            }
        }

        setPermissionsTeamId(null);
    }

    /**
     * @return array{
     *     super-admin: User,
     *     tenant-admin: User,
     *     partner: User,
     *     associate: User,
     *     client: User
     * }
     */
    protected function seedWorkspaceUsers(Tenant $tenant): array
    {
        $workspaceUsers = [
            'super-admin' => User::factory()->create([
                'name' => 'System Overseer',
                'email' => 'super@example.com',
            ]),
            'tenant-admin' => User::factory()->forTenant($tenant)->create([
                'name' => 'Acme Admin',
                'email' => 'admin@example.com',
            ]),
            'partner' => User::factory()->forTenant($tenant)->create([
                'name' => 'Pat Partner',
                'email' => 'partner@example.com',
            ]),
            'associate' => User::factory()->forTenant($tenant)->create([
                'name' => 'Alex Associate',
                'email' => 'associate@example.com',
            ]),
            'client' => User::factory()->forTenant($tenant)->create([
                'name' => 'Casey Client',
                'email' => 'client@example.com',
            ]),
        ];

        foreach ([
            'super-admin' => $tenant->id,
            'tenant-admin' => $tenant->id,
            'partner' => $tenant->id,
            'associate' => $tenant->id,
            'client' => $tenant->id,
        ] as $role => $teamId) {
            setPermissionsTeamId($teamId);
            $workspaceUsers[$role]->assignRole($role);
        }

        return $workspaceUsers;
    }

    /**
     * @param array<int, array{
     *     title: string,
     *     file_name: string,
     *     status: string,
     *     uploaded_by: string,
     *     days_ago: int
     * }> $documents
     * @param array<string, User> $workspaceUsers
     */
    protected function seedMatterDocuments(Tenant $tenant, Matter $matter, array $documents, array $workspaceUsers): void
    {
        foreach ($documents as $index => $documentAttributes) {
            $createdAt = now()->subDays($documentAttributes['days_ago']);
            $uploader = $workspaceUsers[$documentAttributes['uploaded_by']];

            $document = Document::factory()->create([
                'tenant_id' => $tenant->id,
                'matter_id' => $matter->id,
                'uploaded_by' => $uploader->id,
                'title' => $documentAttributes['title'],
                'file_path' => sprintf(
                    'tenants/%s/documents/%s/%s',
                    $tenant->id,
                    $matter->id,
                    $documentAttributes['file_name'],
                ),
                'file_name' => $documentAttributes['file_name'],
                'status' => $documentAttributes['status'],
                'created_at' => $createdAt,
                'updated_at' => $createdAt->copy()->addHour(),
            ]);

            $this->seedDocumentActivity($tenant, $document, $uploader, $createdAt, $index);
        }
    }

    protected function seedDocumentActivity(
        Tenant $tenant,
        Document $document,
        User $uploader,
        CarbonInterface $createdAt,
        int $index,
    ): void
    {
        $this->createAuditLog($tenant, $document, $uploader, 'uploaded', $createdAt, 10 + $index);
        $this->createAuditLog(
            $tenant,
            $document,
            $uploader,
            'viewed',
            $createdAt->copy()->addHours(2),
            30 + $index,
        );

        if (
            $document->status === DocumentStatus::ReadyForReview
            || $document->status === DocumentStatus::Approved
        ) {
            $this->createAuditLog(
                $tenant,
                $document,
                $uploader,
                'updated',
                $createdAt->copy()->addHours(4),
                50 + $index,
            );
        }

        if ($document->status === DocumentStatus::Approved) {
            $this->createAuditLog(
                $tenant,
                $document,
                $uploader,
                'downloaded',
                $createdAt->copy()->addHours(6),
                70 + $index,
            );
        }
    }

    protected function createAuditLog(
        Tenant $tenant,
        Document $document,
        User $user,
        string $action,
        CarbonInterface $createdAt,
        int $ipSuffix,
    ): void {
        AuditLog::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'auditable_type' => Document::class,
            'auditable_id' => $document->id,
            'action' => $action,
            'metadata' => [
                'ip_address' => '10.24.0.'.$ipSuffix,
                'user_agent' => 'database-seeder/workspace-demo',
            ],
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
