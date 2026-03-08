<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        setPermissionsTeamId(null);

        $permissions = [
            'view clients',
            'create clients',
            'edit clients',
            'delete clients',
            'view matters',
            'create matters',
            'edit matters',
            'delete matters',
            'view documents',
            'create documents',
            'edit documents',
            'delete documents',
            'approve documents',
            'manage users',
            'manage tenant',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        Role::findOrCreate('super-admin');

        Role::findOrCreate('tenant-admin')
            ->syncPermissions($permissions);

        Role::findOrCreate('partner')
            ->syncPermissions([
                'view clients',
                'create clients',
                'edit clients',
                'view matters',
                'create matters',
                'edit matters',
                'view documents',
                'create documents',
                'edit documents',
                'approve documents',
                'manage users',
            ]);

        Role::findOrCreate('associate')
            ->syncPermissions([
                'view clients',
                'create clients',
                'edit clients',
                'view matters',
                'create matters',
                'edit matters',
                'view documents',
                'create documents',
                'edit documents',
            ]);

        Role::findOrCreate('client')
            ->syncPermissions([
                'view clients',
                'view matters',
                'view documents',
            ]);

        setPermissionsTeamId(null);
    }
}
