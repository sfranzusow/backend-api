<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (PermissionName::cases() as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName->value,
                'guard_name' => 'web',
            ]);
        }

        $adminRole = Role::firstOrCreate([
            'name' => RoleName::Admin->value,
            'guard_name' => 'web',
        ]);

        $landlordRole = Role::firstOrCreate([
            'name' => RoleName::Landlord->value,
            'guard_name' => 'web',
        ]);

        $tenantRole = Role::firstOrCreate([
            'name' => RoleName::Tenant->value,
            'guard_name' => 'web',
        ]);

        $userRole = Role::firstOrCreate([
            'name' => RoleName::User->value,
            'guard_name' => 'web',
        ]);

        $adminRole->syncPermissions(PermissionName::values());

        $userRole->syncPermissions([
            PermissionName::ProfileViewOwn->value,
            PermissionName::ProfileUpdateOwn->value,
        ]);

        $landlordRole->syncPermissions([
            PermissionName::ProfileViewOwn->value,
            PermissionName::ProfileUpdateOwn->value,
            PermissionName::TenantsViewOwn->value,
            PermissionName::TenantsUpdateOwn->value,
            PermissionName::MessagesViewOwn->value,
            PermissionName::MessagesCreateOwn->value,
            PermissionName::DocumentsViewOwn->value,
        ]);

        $tenantRole->syncPermissions([
            PermissionName::ProfileViewOwn->value,
            PermissionName::ProfileUpdateOwn->value,
            PermissionName::LandlordsViewOwn->value,
            PermissionName::MessagesViewOwn->value,
            PermissionName::MessagesCreateOwn->value,
            PermissionName::InvoicesViewOwn->value,
            PermissionName::DocumentsViewOwn->value,
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
