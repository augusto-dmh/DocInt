<?php

namespace Database\Seeders;

use App\Enums\MatterStatus;
use App\Models\Client;
use App\Models\Matter;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

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

        setPermissionsTeamId($tenant->id);

        $users = [
            ['name' => 'Acme Admin', 'email' => 'admin@example.com', 'role' => 'tenant-admin'],
            ['name' => 'Pat Partner', 'email' => 'partner@example.com', 'role' => 'partner'],
            ['name' => 'Alex Associate', 'email' => 'associate@example.com', 'role' => 'associate'],
            ['name' => 'Casey Client', 'email' => 'client@example.com', 'role' => 'client'],
        ];

        foreach ($users as $attributes) {
            $user = User::factory()
                ->forTenant($tenant)
                ->create([
                    'name' => $attributes['name'],
                    'email' => $attributes['email'],
                ]);

            $user->assignRole($attributes['role']);
        }

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
                    ],
                    [
                        'title' => 'Employment Policy Update',
                        'description' => 'Refresh handbook and termination procedures.',
                        'reference_number' => 'ACME-002',
                        'status' => MatterStatus::OnHold,
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
                    ],
                    [
                        'title' => 'Privacy Complaint Response',
                        'description' => 'Prepare response package for patient privacy complaint.',
                        'reference_number' => 'ACME-004',
                        'status' => MatterStatus::Closed,
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
                    ],
                    [
                        'title' => 'Insurance Coverage Review',
                        'description' => 'Assess cargo and fleet coverage after policy renewal.',
                        'reference_number' => 'ACME-006',
                        'status' => MatterStatus::OnHold,
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
                    ],
                    [
                        'title' => 'Agency Master Services Agreement',
                        'description' => 'Standardize MSA terms for enterprise clients.',
                        'reference_number' => 'ACME-008',
                        'status' => MatterStatus::Open,
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
                Matter::factory()->create([
                    ...$matterAttributes,
                    'tenant_id' => $tenant->id,
                    'client_id' => $client->id,
                ]);
            }
        }

        setPermissionsTeamId(null);
    }
}
